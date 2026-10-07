<?php

namespace App\Planner\Services;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\NevoboStatus;
use App\Planner\Enums\OverlapPolicy;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The day planner backend: moving matches, the change request flow around a
 * move (cancel, confirm in Nevobo, undo) and the handling of Nevobo changes.
 * The day overview itself is built by PlannerDayData.
 */
class PlannerService
{
    public function __construct(
        private readonly AvailabilityChecker $availability,
        private readonly ProvisionalMove $provisional,
        private readonly PlannerDayData $dayData,
    ) {}

    /** @return array<string, mixed> see PlannerDayData::build() */
    public function dayData(ScheduleVersion $version, string $date): array
    {
        return $this->dayData->build($version, $date);
    }

    /**
     * @param  array<string, mixed>  $placement  date, time, duration_minutes, venue_id, court_id, second_court_id
     * @return array{confirmation_required: bool, conflicts: list<array<string, mixed>>, undo_token?: string, provisional?: bool}
     */
    public function move(FixtureMatch $match, array $placement, ?int $userId = null, bool $confirmed = false): array
    {
        if (!$match->version->isDraft()) {
            throw new DomainException(__('Alleen een conceptplanning kan worden gewijzigd.'));
        }

        // The check used to run outside the transaction, so two planners could
        // both get green on the same court and time slot. The matches of this
        // match day are now locked before checking, and only released once
        // the move has been written.
        return DB::transaction(function () use ($match, $placement, $userId, $confirmed): array {
            FixtureMatch::query()->where('version_id', $match->version_id)
                ->whereDate('date', CarbonImmutable::parse($placement['date'])->toDateString())
                ->lockForUpdate()->get();

            return $this->moveLocked($match, $placement, $userId, $confirmed);
        });
    }

    /**
     * Runs inside the transaction of move(); the match day is locked then.
     *
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    private function moveLocked(FixtureMatch $match, array $placement, ?int $userId, bool $confirmed): array
    {
        $check = $this->availability->check($match, $placement);
        $findings = $check['reasons'];
        $hardConflicts = collect($findings)->where('level', ConflictEngine::LEVEL_CONFLICT);
        $policy = UserPreference::query()->where('user_id', $userId)->first()->overlap_policy ?? OverlapPolicy::Warn;
        if ($hardConflicts->isNotEmpty() && $policy === OverlapPolicy::Deny) {
            throw new DomainException(__('Deze plaatsing veroorzaakt een conflict en is niet toegestaan.'));
        }
        if ($hardConflicts->isNotEmpty() && $policy === OverlapPolicy::Warn && !$confirmed) {
            return ['confirmation_required' => true, 'conflicts' => $findings];
        }

        $placementFields = ProvisionalMove::FIELDS;
        // Raw values, so the undo cache holds plain scalars. Columns the model
        // was never loaded with count as null.
        $oldKeys = [
            ...$placementFields, ...array_keys($this->provisional->cleared()),
            'change_status', 'change_requested', 'change_requested_at', 'change_requested_by', 'nevobo_status',
        ];
        $old = Arr::only($match->getAttributes(), $oldKeys) + array_fill_keys($oldKeys, null);
        $old['change_requested'] = (bool) ($old['change_requested'] ?? false);
        $old['nevobo_status'] = ($old['nevobo_status'] ?? null) ?: NevoboStatus::NotApplicable->value;
        $new = collect($placement)->only($placementFields)->all();
        // To another day is a proposal: the match stays where it was, also on
        // the website, until the move is confirmed. Shifting on the same day
        // goes through directly and replaces an open proposal.
        $isProposal = $this->provisional->isProposal($match, $new);
        $match->update(($isProposal ? $this->provisional->columns($new) : $new + $this->provisional->cleared()) + [
            'change_status' => ChangeStatus::Requested,
            'change_requested' => true,
            'change_requested_at' => now(),
            'change_requested_by' => $userId,
            'nevobo_status' => NevoboStatus::Open,
            'away_new_date' => null,
            'away_new_time' => null,
            'away_new_venue' => null,
            'suggested_court_id' => null,
            'suggested_duration_minutes' => null,
            'away_change_status' => AwayChangeStatus::None,
        ]);
        activity('planner-move')->performedOn($match)
            ->withProperties(['old' => $old, 'new' => $new, 'conflicts' => $findings, 'source' => 'dnd', 'provisional' => $isProposal])
            ->log($isProposal ? 'Move proposed' : 'Match moved');

        $token = (string) Str::uuid();
        // Long enough to read the notification, dismiss it and still decide it
        // was wrong after all. Nobody managed that in ten seconds.
        Cache::put("planner-undo:{$token}", ['match_id' => $match->id, 'values' => $old], now()->addMinutes(10));

        return ['confirmation_required' => false, 'conflicts' => $findings, 'undo_token' => $token, 'provisional' => $isProposal];
    }

    public function undo(string $token): bool
    {
        $data = Cache::pull("planner-undo:{$token}");
        if (!$data) {
            return false;
        }
        $match = FixtureMatch::query()->find($data['match_id']);
        if (!$match) {
            return false;
        }
        $match->update($data['values']);
        activity('planner-move')->performedOn($match)->withProperties(['restored' => $data['values']])->log('Move undone');

        return true;
    }

    public function cancelChangeRequest(FixtureMatch $match, ?int $userId = null): void
    {
        if (!$match->version->isDraft()) {
            throw new DomainException(__('Alleen in een conceptplanning kan een wijzigingsverzoek worden geannuleerd.'));
        }
        // The match is still at its old place; only the proposal lapses.
        if ($match->hasProvisionalPlacement()) {
            $old = $match->only(array_keys($this->provisional->cleared()));
            $match->update($this->provisional->cleared() + $this->requestCleared() + ['nevobo_status' => NevoboStatus::NotApplicable]);
            activity('planner-move')->performedOn($match)->causedBy($userId)
                ->withProperties(['old' => $old])->log('Move proposal rejected');

            return;
        }
        $venue = $this->provisional->venueForName($match->original_venue);
        $court = $venue && $match->original_court
            ? $venue->courts()->where('name', $match->original_court)->first()
            : null;
        $old = $match->only(['date', 'time', 'venue_id', 'court_id', 'second_court_id']);
        $match->update([
            'date' => $match->original_date,
            'time' => $match->original_time,
            'venue_id' => $venue?->id,
            'court_id' => $court?->id,
            'second_court_id' => null,
            'nevobo_status' => NevoboStatus::NotApplicable,
        ] + $this->requestCleared());
        activity('planner-move')->performedOn($match)->causedBy($userId)
            ->withProperties(['old' => $old, 'restored' => $match->only(array_keys($old))])->log('Move request cancelled');
    }

    public function markNevoboApplied(FixtureMatch $match, ?int $userId = null): void
    {
        if (!$match->change_requested && $match->change_status !== ChangeStatus::Requested) {
            throw new DomainException(__('Deze wedstrijd heeft geen openstaand wijzigingsverzoek.'));
        }
        $match->loadMissing(['venue', 'court']);
        // Only now that it is approved does the match really go to its new place.
        $match->update(($match->hasProvisionalPlacement() ? $this->provisional->finalize($match) : [
            'original_date' => $match->date,
            'original_time' => $match->time,
            'original_venue' => $match->venue?->name,
            'original_court' => $match->court?->name,
        ]) + $this->requestCleared() + ['nevobo_status' => NevoboStatus::Applied]);
        activity('planner-nevobo')->performedOn($match)->causedBy($userId)->log('Change marked as applied in Nevobo');
    }

    /**
     * Accept the schedule Nevobo parked on this match: their values become both
     * the planning and the new baseline, so the match reads as unchanged again.
     */
    public function acceptNevoboChange(FixtureMatch $match, ?int $userId = null): void
    {
        $venue = $match->nevobo_new_venue
            ? Venue::query()->where('name', $match->nevobo_new_venue)->first()
            : null;

        $match->update([
            'date' => $match->nevobo_new_date ?? $match->date,
            'time' => $match->nevobo_new_time ?? $match->time,
            'venue_id' => $venue->id ?? $match->venue_id,
            'original_date' => $match->nevobo_new_date ?? $match->date,
            'original_time' => $match->nevobo_new_time ?? $match->time,
            'original_venue' => $match->nevobo_new_venue ?? $match->original_venue,
        ] + $this->nevoboFlagCleared()
            // Nevobo chose another place than proposed; that proposal lapses.
            + ($match->hasProvisionalPlacement() ? $this->provisional->cleared() + $this->requestCleared() : []));

        activity('planner-nevobo')->performedOn($match)->causedBy($userId)->log('Nevobo change accepted');
    }

    /**
     * Keep our own planning. The Nevobo schedule still becomes the baseline, so
     * the match keeps showing as deviating from what the federation has on record.
     */
    public function ignoreNevoboChange(FixtureMatch $match, ?int $userId = null): void
    {
        $match->update([
            'original_date' => $match->nevobo_new_date ?? $match->original_date,
            'original_time' => $match->nevobo_new_time ?? $match->original_time,
            'original_venue' => $match->nevobo_new_venue ?? $match->original_venue,
        ] + $this->nevoboFlagCleared());

        activity('planner-nevobo')->performedOn($match)->causedBy($userId)
            ->log('Nevobo change seen, own planning kept');
    }

    /**
     * Stores a proposal from the planning assistant without touching the planning.
     *
     * @param  array<string, mixed>  $proposal  date, time, duration_minutes?, venue_id, court_id
     */
    public function saveProposal(FixtureMatch $match, array $proposal, ?int $userId = null): void
    {
        if (!$match->version->isDraft()) {
            throw new DomainException(__('Alleen in een conceptplanning kan een voorstel worden opgeslagen.'));
        }

        $venue = Venue::query()->whereKey($proposal['venue_id'])->firstOrFail();
        $court = $venue->courts()->whereKey($proposal['court_id'])->firstOrFail();
        $match->update([
            'away_new_date' => $proposal['date'],
            'away_new_time' => $proposal['time'],
            'away_new_venue' => $venue->name,
            'suggested_court_id' => $court->id,
            'suggested_duration_minutes' => $proposal['duration_minutes'] ?? $match->duration_minutes,
            'away_change_status' => AwayChangeStatus::InProgress,
        ]);
        activity('planner-move')->performedOn($match)->causedBy($userId)
            ->withProperties(['proposal' => $proposal + ['venue' => $venue->name, 'court' => $court->name], 'source' => 'proposal'])
            ->log('Move proposal saved');
    }

    public function cancelProposal(FixtureMatch $match, ?int $userId = null): void
    {
        $match->update([
            'away_new_date' => null,
            'away_new_time' => null,
            'away_new_venue' => null,
            'suggested_court_id' => null,
            'suggested_duration_minutes' => null,
            'away_change_status' => AwayChangeStatus::None,
        ]);
        activity('planner-move')->performedOn($match)->causedBy($userId)
            ->withProperties(['source' => 'proposal'])->log('Move proposal removed');
    }

    /** @return array<string, mixed> */
    private function nevoboFlagCleared(): array
    {
        return [
            'nevobo_change_status' => NevoboChangeStatus::None,
            'nevobo_changed_at' => null,
            'nevobo_previous_date' => null,
            'nevobo_previous_time' => null,
            'nevobo_previous_venue' => null,
            'nevobo_new_date' => null,
            'nevobo_new_time' => null,
            'nevobo_new_venue' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function requestCleared(): array
    {
        return [
            'change_status' => null,
            'change_requested' => false,
            'change_requested_at' => null,
            'change_requested_by' => null,
        ];
    }
}
