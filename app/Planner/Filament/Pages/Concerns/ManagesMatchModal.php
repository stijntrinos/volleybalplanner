<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\OverlapPolicy;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use App\Planner\Services\ConflictEngine;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * State and actions for the create/edit match modal.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait ManagesMatchModal
{
    /** null = closed, 0 = new match, otherwise the match being edited. */
    public ?int $modalMatchId = null;

    public ?int $modalVenueId = null;

    public ?int $modalCourtId = null;

    public ?int $modalSecondCourtId = null;

    public string $modalDate = '';

    public string $modalTime = '';

    public int $modalDuration = 120;

    public string $modalReferee = '';

    public string $modalScorer = '';

    public string $modalNotes = '';

    public string $modalMatchCode = '';

    public string $modalHomeTeam = '';

    public string $modalAwayTeam = '';

    public function openMatch(int $matchId): void
    {
        $this->requireMatchPermission('View');
        $match = $this->matchInVersion($matchId);
        $this->modalMatchId = $match->id;
        $this->modalVenueId = $match->venue_id;
        $this->modalCourtId = $match->court_id;
        $this->modalSecondCourtId = $match->second_court_id;
        $this->modalDate = $match->date?->format('d-m-Y') ?: '';
        $this->modalTime = substr((string) $match->time, 0, 5);
        $this->modalDuration = (int) $match->duration_minutes;
        $this->modalReferee = (string) $match->referee;
        $this->modalScorer = (string) $match->scorer;
        $this->modalNotes = (string) $match->notes;
        $this->modalMatchCode = (string) $match->match_code;
        $this->modalHomeTeam = (string) $match->home_team_name;
        $this->modalAwayTeam = (string) $match->away_team_name;
    }

    public function openNewMatch(): void
    {
        $this->requireMatchPermission('Create');
        $version = $this->versionId ? ScheduleVersion::query()->find($this->versionId) : null;
        if (!$version?->isDraft()) {
            Notification::make()->title(__('Selecteer eerst een conceptplanning'))->warning()->send();

            return;
        }
        $this->modalMatchId = 0;
        $this->modalVenueId = Venue::query()->where('is_active', true)->where('for_matches', true)->inPlanningOrder()->value('id');
        $this->modalCourtId = $this->firstCourtId($this->modalVenueId);
        $this->modalSecondCourtId = null;
        $this->modalDate = CarbonImmutable::parse($this->date)->format('d-m-Y');
        $this->modalTime = '';
        $this->modalDuration = 105;
        $this->modalReferee = '';
        $this->modalScorer = '';
        $this->modalNotes = '';
        $this->modalMatchCode = '';
        $this->modalHomeTeam = '';
        $this->modalAwayTeam = '';
    }

    public function closeMatch(): void
    {
        $this->reset([
            'modalMatchId', 'modalVenueId', 'modalCourtId', 'modalSecondCourtId',
            'modalDate', 'modalTime', 'modalReferee', 'modalScorer', 'modalNotes',
            'modalMatchCode', 'modalHomeTeam', 'modalAwayTeam',
        ]);
        $this->modalDuration = 120;
    }

    public function updatedModalVenueId(): void
    {
        $this->modalCourtId = $this->firstCourtId($this->modalVenueId);
        $this->modalSecondCourtId = null;
    }

    /**
     * Dragging went through the conflict check, saving in this modal did not:
     * via the form you could put a match on top of another without anything
     * saying so. Hence the same check and the same confirmation.
     */
    public function saveMatch(bool $confirmed = false): void
    {
        $this->requireMatchPermission($this->modalMatchId ? 'Update' : 'Create');
        $version = ScheduleVersion::query()->findOrFail($this->versionId);
        $match = $this->modalMatchId
            ? FixtureMatch::query()->where('version_id', $version->id)->findOrFail($this->modalMatchId)
            : null;
        $isNew = !$match;
        if (!$version->isDraft()) {
            Notification::make()->title(__('Deze planning is alleen-lezen'))->danger()->send();

            return;
        }
        $clubId = app(CurrentClub::class)->id();
        $values = $this->validate([
            'modalVenueId' => ['required', 'integer', Rule::exists('planner_venues', 'id')->where('club_id', $clubId)],
            'modalCourtId' => ['required', 'integer', Rule::exists('planner_courts', 'id')->where('club_id', $clubId)],
            'modalSecondCourtId' => ['nullable', 'integer', 'different:modalCourtId', Rule::exists('planner_courts', 'id')->where('club_id', $clubId)],
            'modalDate' => ['required', 'date_format:d-m-Y'],
            'modalTime' => ['required', 'date_format:H:i'],
            'modalDuration' => ['required', 'integer', 'in:'.implode(',', FixtureMatch::DURATION_OPTIONS)],
            'modalReferee' => ['nullable', 'string', 'max:255'],
            'modalScorer' => ['nullable', 'string', 'max:255'],
            'modalNotes' => ['nullable', 'string', 'max:2000'],
            'modalMatchCode' => ['required', 'string', 'max:100'],
            'modalHomeTeam' => ['required', 'string', 'max:255'],
            'modalAwayTeam' => ['required', 'string', 'max:255'],
        ]);
        $duplicate = FixtureMatch::query()->where('version_id', $version->id)->where('match_code', $values['modalMatchCode'])
            ->when($match, fn ($query) => $query->whereKeyNot($match->id))->exists();
        if ($duplicate) {
            $this->addError('modalMatchCode', __('Deze wedstrijdcode bestaat al in deze planning.'));

            return;
        }
        $courtIds = array_filter([$values['modalCourtId'], $values['modalSecondCourtId'] ?? null]);
        $validCourts = Court::query()->where('venue_id', $values['modalVenueId'])->whereIn('id', $courtIds)->count();
        if ($validCourts !== count($courtIds)) {
            $this->addError('modalCourtId', __('Kies een veld uit de geselecteerde sporthal.'));

            return;
        }
        $matchValues = [
            'venue_id' => $values['modalVenueId'], 'court_id' => $values['modalCourtId'],
            'second_court_id' => $values['modalSecondCourtId'] ?? null,
            'date' => CarbonImmutable::createFromFormat('d-m-Y', $values['modalDate'])->toDateString(),
            'time' => $values['modalTime'], 'duration_minutes' => $values['modalDuration'],
            'referee' => $values['modalReferee'] ?: null, 'scorer' => $values['modalScorer'] ?: null,
            'notes' => $values['modalNotes'] ?: null,
            'match_code' => $values['modalMatchCode'], 'home_team_name' => $values['modalHomeTeam'],
            'away_team_name' => $values['modalAwayTeam'],
        ];
        try {
            $conflicts = $this->conflictsForModal($version, $match, $matchValues);
        } catch (DomainException $exception) {
            $this->showError(__('Opslaan niet mogelijk'), $exception->getMessage());

            return;
        }
        if ($conflicts && !$confirmed) {
            $this->confirmation = $conflicts;

            return;
        }

        if ($match) {
            $match->update($matchValues);
        } else {
            $match = $version->matches()->create($matchValues + [
                'match_type' => MatchType::Home, 'created_by' => auth()->id(),
            ]);
        }
        activity('planner-planning')->performedOn($match)->causedBy(auth()->id())
            ->log($isNew ? 'Match added via home matches' : 'Match edited via home matches');
        $this->confirmation = [];
        $this->closeMatch();
        $this->load();
        Notification::make()->title($isNew ? __('Wedstrijd toegevoegd') : __('Wedstrijd opgeslagen'))->success()->send();
    }

    /**
     * The conflicts of this placement, or null when nothing is wrong. Under
     * the "deny" policy saving does not go through at all.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function conflictsForModal(ScheduleVersion $version, ?FixtureMatch $match, array $values): ?array
    {
        // A new match does not exist yet, so the check gets an unsaved
        // instance with the same data.
        $subject = $match ?? new FixtureMatch([
            'version_id' => $version->id,
            'home_team_name' => $values['home_team_name'], 'away_team_name' => $values['away_team_name'],
            'duration_minutes' => $values['duration_minutes'], 'match_type' => MatchType::Home,
        ]);
        $subject->club_id = $version->club_id;
        $subject->version_id = $version->id;

        $findings = app(AvailabilityChecker::class)->check($subject, [
            'date' => $values['date'], 'time' => $values['time'], 'duration_minutes' => $values['duration_minutes'],
            'venue_id' => $values['venue_id'], 'court_id' => $values['court_id'],
            'second_court_id' => $values['second_court_id'],
        ], null, false)['reasons'];

        $hard = collect($findings)->where('level', ConflictEngine::LEVEL_CONFLICT);
        if ($hard->isEmpty()) {
            return null;
        }

        $policy = UserPreference::query()->where('user_id', auth()->id())->first()->overlap_policy ?? OverlapPolicy::Warn;
        if ($policy === OverlapPolicy::Deny) {
            throw new DomainException($hard->first()['message'] ?? __('Deze plaatsing veroorzaakt een conflict.'));
        }

        return [
            'source' => 'modal',
            'time' => $values['time'],
            'description' => trim($values['home_team_name'].' – '.$values['away_team_name'], ' –'),
            'conflicts' => $findings,
        ];
    }

    public function deleteMatch(): void
    {
        $this->requireMatchPermission('Delete');
        $match = $this->matchInVersion((int) $this->modalMatchId);
        if (!$match->version->isDraft()) {
            return;
        }
        activity('planner-planning')->performedOn($match)->causedBy(auth()->id())
            ->withProperties($match->toArray())->log('Match deleted via home matches');
        $match->delete();
        $this->closeMatch();
        $this->load();
        Notification::make()->title(__('Wedstrijd verwijderd'))->success()->send();
    }

    /**
     * A match on two courts always occupies the first two courts of the
     * venue, so the second court is derived rather than chosen.
     */
    public function setTwoCourts(bool $enabled): void
    {
        if (!$enabled) {
            $this->modalSecondCourtId = null;

            return;
        }

        $courts = $this->modalCourts->keys()->values();
        $this->modalCourtId = $courts->has(0) ? (int) $courts[0] : $this->modalCourtId;
        $this->modalSecondCourtId = $courts->has(1) ? (int) $courts[1] : null;
    }

    /** @return Collection<int, string> */
    public function getModalVenuesProperty(): Collection
    {
        return Venue::query()->where('is_active', true)->where('for_matches', true)->inPlanningOrder()->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function getModalCourtsProperty(): Collection
    {
        return Court::query()->when($this->modalVenueId, fn ($query) => $query->where('venue_id', $this->modalVenueId))
            ->where('is_active', true)->where('is_training_court', false)->orderBy('sort_order')->pluck('name', 'id');
    }

    /** @return Collection<string, string> */
    public function getModalOfficialsProperty(): Collection
    {
        return Team::query()->whereNotNull('code')->where('code', '!=', '')->orderBy('code')->pluck('code', 'code');
    }

    public function getModalMatchProperty(): ?FixtureMatch
    {
        return $this->modalMatchId
            ? FixtureMatch::query()->with(['venue', 'court'])->where('version_id', $this->versionId)->find($this->modalMatchId)
            : null;
    }

    private function firstCourtId(?int $venueId): ?int
    {
        return Court::query()->where('venue_id', $venueId)
            ->where('is_active', true)->where('is_training_court', false)->orderBy('sort_order')->value('id');
    }
}
