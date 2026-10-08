<?php

namespace App\Planner\Services;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\ReadOnlyClub;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

/**
 * A team's contact person submits a request to change a match or a duty via a
 * shared link (module change_requests; duty requests also need duties). This
 * service knows what such a contact may see and records the outcome. Every
 * method works on the current club.
 */
class ChangeRequestService
{
    /**
     * The same match name comes by dozens of times and always yields the
     * same codes.
     *
     * @var array<string, list<string>>
     */
    private array $codesPerName = [];

    public function __construct(private readonly ConflictEngine $conflicts) {}

    /**
     * Whether the form accepts requests right now. Outside the season the
     * committee closes it; the link keeps working and explains that there is
     * nothing to submit for now.
     */
    public function isOpen(): bool
    {
        // A read-only club takes no new requests: nobody could handle them.
        if (ReadOnlyClub::active()) {
            return false;
        }

        return (bool) (PlannerSetting::query()->value('requests_open') ?? true);
    }

    /**
     * The teams that have something to move. Putting a team without an
     * upcoming match in the list only leads to an empty next screen.
     *
     * @return Collection<int, Team>
     */
    public function teams(): Collection
    {
        $teams = Team::query()->get();
        $involved = $this->upcomingMatches()
            ->flatMap(fn (FixtureMatch $match): array => $this->codesOf($match, $teams))
            ->unique();

        // Sorting in PHP: by code the database sorts "DS 10" between DS 1 and
        // DS 2, and mixes up the youth teams.
        return $teams
            ->filter(fn (Team $team): bool => $involved->contains(TeamCode::key($team->code)))
            ->sortBy(fn (Team $team): string => $this->teamSortKey($team))
            ->values();
    }

    /**
     * When the code gives no category it falls back on the name: that is in
     * the list, so it is what the contact reads. A team like "Club Sponsor
     * DS 1" belongs with the women, not at the bottom.
     */
    private function teamSortKey(Team $team): string
    {
        $byCode = TeamCode::sortKey($team->code);

        return str_starts_with($byCode, '99|')
            ? TeamCode::sortKey($team->name)
            : $byCode;
    }

    /**
     * This team's matches from today, home and away. Away matches are listed
     * too: a team wants those moved just as well, it only goes through the
     * opponent.
     *
     * @return Collection<int, FixtureMatch>
     */
    public function matches(int $teamId): Collection
    {
        $teams = Team::query()->get();
        $team = $teams->firstWhere('id', $teamId);
        if (!$team instanceof Team) {
            return new Collection;
        }
        $key = TeamCode::key($team->code);

        return $this->upcomingMatches()
            ->filter(fn (FixtureMatch $match): bool => in_array($key, $this->codesOf($match, $teams), true))
            ->values();
    }

    /**
     * Only teams with an upcoming, assigned duty. That way a contact never
     * ends up on an empty screen after the first choice.
     *
     * @return Collection<int, Team>
     */
    public function dutyTeams(): Collection
    {
        $version = $this->version();
        if (!$version || !$this->dutiesEnabled()) {
            return new Collection;
        }

        $teams = Team::query()->get();
        $codes = FixtureMatch::query()
            ->where('version_id', $version->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->get(['referee', 'scorer'])
            ->flatMap(fn (FixtureMatch $match): array => [$match->referee, $match->scorer])
            ->filter()
            ->map(fn (string $code): string => TeamCode::key($code))
            ->unique();
        $teamIds = HallDuty::query()
            ->where('version_id', $version->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->pluck('team_id');

        return $teams
            ->filter(fn (Team $team): bool => $teamIds->contains($team->id)
                || $codes->contains(TeamCode::key($team->code)))
            ->sortBy(fn (Team $team): string => $this->teamSortKey($team))
            ->values();
    }

    /**
     * The concrete referee, scorer and hall duties assigned to one team. The
     * key holds type plus id, because one match can give two different duties.
     *
     * @return SupportCollection<int, array{
     *     key: string, type: string, label: string, description: string,
     *     date: string, time: string, location: ?string, match_id: ?int,
     *     match_code: ?string, hall_duty_id: ?int
     * }>
     */
    public function duties(int $teamId): SupportCollection
    {
        $version = $this->version();
        $team = Team::query()->find($teamId);
        if (!$version || !$team instanceof Team || !$this->dutiesEnabled()) {
            return collect();
        }

        $duties = collect();
        $matches = FixtureMatch::query()
            ->where('version_id', $version->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->where(fn ($query) => $query->whereNotNull('referee')->orWhereNotNull('scorer'))
            ->with(['venue', 'court'])
            ->orderBy('date')->orderBy('time')->get();

        foreach ($matches as $match) {
            if (TeamCode::equals($match->referee, $team->code)) {
                $duties->push($this->matchDuty($match, DutyType::Referee));
            }
            if (TeamCode::equals($match->scorer, $team->code)) {
                $duties->push($this->matchDuty($match, DutyType::Scorer));
            }
        }

        HallDuty::query()
            ->where('version_id', $version->id)
            ->where('team_id', $team->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->with('venue')
            ->orderBy('date')->orderBy('start_time')->get()
            ->each(function (HallDuty $duty) use ($duties): void {
                $duties->push([
                    'key' => DutyType::HallDuty->value.':'.$duty->id,
                    'type' => DutyType::HallDuty->value,
                    'label' => DutyType::HallDuty->getLabel(),
                    'description' => $duty->venue->name,
                    'date' => $duty->date->toDateString(),
                    'time' => substr((string) $duty->start_time, 0, 5).'–'.substr((string) $duty->end_time, 0, 5),
                    'location' => $duty->venue->name,
                    'match_id' => null,
                    'match_code' => null,
                    'hall_duty_id' => $duty->id,
                ]);
            });

        return $duties
            ->sortBy(fn (array $duty): string => $duty['date'].' '.$duty['time'].' '.$duty['type'])
            ->values();
    }

    /** @return array<string, mixed> */
    private function matchDuty(FixtureMatch $match, DutyType $type): array
    {
        $location = collect([$match->venue?->name, $match->court?->name])->filter()->implode(' · ');

        return [
            'key' => $type->value.':'.$match->id,
            'type' => $type->value,
            'label' => $type->getLabel(),
            'description' => trim($match->home_team_name.' – '.$match->away_team_name, ' –'),
            'date' => $match->date->toDateString(),
            'time' => substr((string) $match->time, 0, 5),
            'location' => $location !== '' ? $location : null,
            'match_id' => $match->id,
            'match_code' => $match->match_code,
            'hall_duty_id' => null,
        ];
    }

    /**
     * Which of our teams belong to this match. Not only by team id: team
     * names come as free text from imports and RSS, and a match without a
     * linked team made the team disappear from the list. The name comparison
     * goes through the ConflictEngine, which already knows how to tell an own
     * team from an opponent with the same code.
     *
     * @param  Collection<int, Team>  $teams
     * @return list<string>
     */
    private function codesOf(FixtureMatch $match, Collection $teams): array
    {
        $codes = $teams
            ->filter(fn (Team $team): bool => in_array($team->id, array_filter([$match->home_team_id, $match->away_team_id]), true))
            ->map(fn (Team $team): string => TeamCode::key($team->code))
            ->all();

        foreach ([$match->home_team_name, $match->away_team_name] as $name) {
            foreach ($this->codesFromName((string) $name, $teams) as $code) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique(array_filter($codes)));
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<string>
     */
    private function codesFromName(string $name, Collection $teams): array
    {
        return $this->codesPerName[$name] ??= array_map(
            fn (string $code): string => TeamCode::key($code),
            $this->conflicts->detectTeams($name, $teams),
        );
    }

    /** @return Collection<int, FixtureMatch> */
    private function upcomingMatches(): Collection
    {
        $version = $this->version();
        if (!$version) {
            return new Collection;
        }

        return FixtureMatch::query()
            ->where('version_id', $version->id)
            ->whereDate('date', '>=', now()->toDateString())
            ->with(['venue', 'court'])
            ->orderBy('date')->orderBy('time')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data  match_id, team_id, name, email, phone?, contact_preference?,
     *                                      reason, reason_other?, explanation, proposed_date?, proposed_time?
     */
    public function submit(array $data): ChangeRequest
    {
        $match = FixtureMatch::query()->findOrFail($data['match_id']);

        $request = ChangeRequest::query()->create([
            'match_id' => $match->id,
            'match_code' => $match->match_code,
            'team_id' => $data['team_id'],
            'kind' => ChangeRequestKind::Match,
            ...$this->contactDetails($data),
            'reason' => $data['reason'],
            'reason_other' => $data['reason_other'] ?? null,
            'explanation' => $data['explanation'],
            'proposed_date' => $data['proposed_date'] ?? null,
            'proposed_time' => ($data['proposed_date'] ?? null) ? ($data['proposed_time'] ?? null) : null,
            'status' => ChangeRequestStatus::Open,
        ]);

        // Fetch again: the encrypted fields are only decrypted when read, so
        // the model just created still carries ciphertext.
        return $request->fresh() ?? $request;
    }

    /**
     * @param  array<string, mixed>  $data  team_id, duty_key (a key from duties()), contact details,
     *                                      reason, reason_other?, explanation
     */
    public function submitDuty(array $data): ChangeRequest
    {
        $duty = $this->duties((int) $data['team_id'])->firstWhere('key', $data['duty_key'] ?? null);

        if (!is_array($duty)) {
            throw ValidationException::withMessages([
                'dutyKey' => __('Deze FTZ-dienst is niet meer beschikbaar. Kies opnieuw.'),
            ]);
        }

        $request = ChangeRequest::query()->create([
            'match_id' => $duty['match_id'],
            'match_code' => $duty['match_code'],
            'hall_duty_id' => $duty['hall_duty_id'],
            'team_id' => $data['team_id'],
            'kind' => ChangeRequestKind::Duty,
            'duty_type' => $duty['type'],
            'duty_date' => $duty['date'],
            'duty_time' => $duty['time'],
            'duty_location' => $duty['location'],
            'duty_description' => $duty['description'],
            ...$this->contactDetails($data),
            'reason' => $data['reason'],
            'reason_other' => $data['reason_other'] ?? null,
            'explanation' => $data['explanation'],
            'status' => ChangeRequestStatus::Open,
        ]);

        return $request->fresh() ?? $request;
    }

    /**
     * Name, email and how the requester wants to be contacted. Without a
     * number WhatsApp falls back on email; the form already demands a number then.
     *
     * @param  array<string, mixed>  $data
     * @return array{name: string, email: string, phone: ?string, contact_preference: ContactPreference}
     */
    private function contactDetails(array $data): array
    {
        $phone = trim((string) ($data['phone'] ?? '')) ?: null;
        $preference = ContactPreference::tryFrom((string) (($data['contact_preference'] ?? null) instanceof ContactPreference
            ? $data['contact_preference']->value
            : ($data['contact_preference'] ?? '')));

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'contact_preference' => $preference === ContactPreference::Whatsapp && $phone
                ? ContactPreference::Whatsapp
                : ContactPreference::Email,
        ];
    }

    public function handle(ChangeRequest $request, ChangeRequestStatus|string $status, ?string $note, ?int $userId): ChangeRequest
    {
        $status = $status instanceof ChangeRequestStatus ? $status : ChangeRequestStatus::from($status);
        $request->update([
            'status' => $status,
            'note' => $note,
            'handled_at' => now(),
            'handled_by' => $userId,
        ]);

        activity('planner-change-request')->performedOn($request)->causedBy($userId)
            ->withProperties(['status' => $status->value])->log('Change request handled');

        return $request;
    }

    /**
     * The match codes with an open or parked request: parked is not decided
     * yet, so the marker should stay. By code and not by id: duplicating a
     * schedule version copies the match rows to new ids, and then the marker
     * would disappear in the planner.
     *
     * @return list<string>
     */
    public function openRequestCodes(): array
    {
        if (!app(CurrentClub::class)->require()->hasFeature(PlannerFeature::ChangeRequests)) {
            return [];
        }

        return ChangeRequest::query()
            ->where('kind', ChangeRequestKind::Match)
            ->whereIn('status', ChangeRequestStatus::undecided())
            ->pluck('match_code')->filter()->unique()->values()->all();
    }

    private function dutiesEnabled(): bool
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Duties);
    }

    /**
     * The planning the club uses publicly. The forms are public, so a draft
     * never shows here: without a published planning there is nothing to ask.
     */
    private function version(): ?ScheduleVersion
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->latest('id')->first();
    }
}
