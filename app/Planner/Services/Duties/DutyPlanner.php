<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates, validates and saves the duty assignment (referee, scorer, hall
 * duty) of a schedule version. Works on the current club.
 *
 * A draft is ['matches' => list<match row>, 'hall_duties' => list<hall duty row>]
 * as returned by generate().
 */
class DutyPlanner
{
    /** Only fill empty duties. */
    public const MODE_FILL = 'fill';

    /** Also reassign what the generator assigned before (the *_auto flags). */
    public const MODE_REDISTRIBUTE = 'redistribute';

    /** Referee value meaning "the federation appoints the referee". */
    public const NEVOBO = 'Nevobo';

    public function __construct(private readonly RuleEngine $rules) {}

    /**
     * @return array{
     *     matches: list<array{id: int, date: string, time: string, venue_id: ?int, code: ?string, venue: ?string, court: ?string, home: ?string, away: ?string, referee: ?string, scorer: ?string, suggested_referee: ?string, suggested_scorer: ?string, locked: array{referee: bool, scorer: bool}}>,
     *     hall_duties: list<array{date: string, venue_id: int, venue: ?string, start: string, end: string, team_code: ?string, suggested_team: ?string, locked: bool}>,
     *     tallies: array<int, array{referee: int, scorer: int, hall_duty: int, total: int, used: int}>
     * }
     */
    public function generate(ScheduleVersion $version, string $mode = self::MODE_FILL, ?string $from = null, ?string $to = null, ?int $venueId = null): array
    {
        $matches = $version->matches()->with(['venue', 'court', 'homeTeam'])->whereNotNull('date')->whereNotNull('time')
            ->orderBy('date')->orderBy('time')->get();
        $home = $matches->where('match_type', MatchType::Home)->where('is_sitting_volleyball', false)
            ->when($from, fn (Collection $c) => $c->filter(fn (FixtureMatch $match): bool => $match->date->toDateString() >= $from))
            ->when($to, fn (Collection $c) => $c->filter(fn (FixtureMatch $match): bool => $match->date->toDateString() <= $to))
            ->when($venueId, fn (Collection $c) => $c->where('venue_id', $venueId))
            ->values();
        $teams = Team::query()->select(Team::PLANNER_COLUMNS)->where('include_in_duties', true)->get();
        $byDate = DutySlots::byDate($matches);
        $byWeekday = $this->trainings()->groupBy('weekday');
        $settings = PlannerSetting::current();
        $maxPerDay = $this->rules->rules()->maxDutiesPerDay();
        $points = $this->pointsPerDuty($settings);
        $withPoints = $this->usesVolunteerPoints();
        // Per playing day, who has already had a turn. The sorting in pick()
        // puts a team with few points on top every time, which would otherwise
        // give that team an entire Saturday in a row.
        $dayDuties = [];
        $tallies = $teams->mapWithKeys(fn (Team $team): array => [$team->id => [
            'referee' => 0, 'scorer' => 0, 'hall_duty' => 0,
            'total' => $withPoints ? (int) $team->volunteer_points_earned : 0,
            'used' => $withPoints ? (int) $team->volunteer_points_used : 0,
        ]])->all();

        // A playing day in the past does not need filling: what is there, was
        // played. Otherwise the planner kept proposing a team for every
        // Saturday already played.
        $today = CarbonImmutable::now()->toDateString();

        $result = ['matches' => [], 'hall_duties' => []];
        foreach ($home as $match) {
            $slot = DutySlots::forMatch($match);
            $dayMatches = $byDate[$slot['date']] ?? collect();
            $dayTrainings = $byWeekday[Weekday::name($slot['date'])] ?? collect();
            $taken = $dayDuties[$slot['date']] ?? [];
            $referee = $match->referee;
            $scorer = $match->scorer;
            $past = $slot['date'] < $today;
            if (!$past && (!$referee || ($mode === self::MODE_REDISTRIBUTE && $match->referee_auto))) {
                $referee = ($match->nevobo_referee || $match->homeTeam?->provides_referee)
                    ? self::NEVOBO
                    : $this->pick($teams, DutyType::Referee, $slot, $dayMatches, $dayTrainings, $tallies, $points, $taken, $maxPerDay);
            }
            // Book right away and not after the scorer: otherwise both choices
            // see the same standings and one team gets two duties from one
            // match. What was already there or is locked counts too, because
            // the limit is about the duties a team has that day, not this run.
            $this->bookDayDuty($taken, $referee);
            if (!$past && (!$scorer || ($mode === self::MODE_REDISTRIBUTE && $match->scorer_auto))) {
                $scorer = $match->homeTeam?->scores_own_match
                    ? $match->homeTeam->code
                    : $this->pick($teams, DutyType::Scorer, $slot, $dayMatches, $dayTrainings, $tallies, $points, $taken, $maxPerDay);
            }
            $this->bookDayDuty($taken, $scorer);
            $dayDuties[$slot['date']] = $taken;
            $result['matches'][] = [
                'id' => $match->id, 'date' => $slot['date'], 'time' => $slot['start'],
                'venue_id' => $match->venue_id, 'code' => $match->match_code,
                'venue' => $match->venue?->name, 'court' => $match->court?->name,
                'home' => $match->home_team_name, 'away' => $match->away_team_name,
                'referee' => $referee, 'scorer' => $scorer,
                'suggested_referee' => $referee, 'suggested_scorer' => $scorer,
                'locked' => [
                    'referee' => filled($match->referee) && !$match->referee_auto,
                    'scorer' => filled($match->scorer) && !$match->scorer_auto,
                ],
            ];
        }

        $existing = $version->hallDuties()->with('team')
            ->when($from, fn ($query) => $query->whereDate('date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('date', '<=', $to))
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->get()
            ->keyBy(fn (HallDuty $duty): string => DutySlots::hallDutyKey($duty->date->toDateString(), $duty->venue_id, $duty->start_time));

        foreach (DutySlots::playingRounds($home) as $round) {
            $slot = $round['slot'] + ['match' => $round['matches']->first()];
            // A hall duty belongs to a venue. Without a venue at the match
            // there is nothing to be on duty in.
            if (!$slot['venue_id'] || !$settings->hasHallDutyOnDate($slot['date'])) {
                continue;
            }

            $taken = $dayDuties[$slot['date']] ?? [];
            $existingDuty = $existing->get(DutySlots::hallDutyKey($slot['date'], $slot['venue_id'], $slot['start']));
            $existingTeam = $existingDuty?->team?->code;
            $past = $slot['date'] < $today;
            // A played day without duty does not get one anymore.
            if ($past && blank($existingTeam)) {
                continue;
            }
            // Same trade-off as for referee and scorer: only what the
            // generator assigned itself may be redistributed.
            $recalculate = !$past
                && (blank($existingTeam) || ($mode === self::MODE_REDISTRIBUTE && $existingDuty->team_auto));
            $team = $recalculate
                ? $this->pick(
                    $teams, DutyType::HallDuty, $slot,
                    $byDate[$slot['date']] ?? collect(),
                    $byWeekday[Weekday::name($slot['date'])] ?? collect(),
                    $tallies, $points, $taken, $maxPerDay,
                )
                : $existingTeam;
            $this->bookDayDuty($taken, $team);
            $dayDuties[$slot['date']] = $taken;
            $result['hall_duties'][] = $round['slot'] + [
                'team_code' => $team,
                'suggested_team' => $team,
                'locked' => filled($existingTeam) && !$existingDuty->team_auto,
            ];
        }

        $result['tallies'] = $tallies;

        return $result;
    }

    /**
     * Applies a draft: the referee/scorer of each match row and the hall
     * duties within the given range (which are deleted and recreated).
     *
     * @param  bool  $singleVenue  Whether $venueId limits the range. Without
     *                             this flag an empty $venueId means "all
     *                             venues"; applying a block without venue
     *                             would then take along the hall duties of
     *                             the other venues on the same day.
     */
    public function save(ScheduleVersion $version, array $draft, ?int $userId = null, ?string $from = null, ?string $to = null, ?int $venueId = null, bool $singleVenue = false): void
    {
        DB::transaction(function () use ($version, $draft, $userId, $from, $to, $venueId, $singleVenue): void {
            foreach ($draft['matches'] ?? [] as $row) {
                $match = $version->matches()->findOrFail($row['id']);
                $referee = $row['referee'] ?: null;
                $scorer = $row['scorer'] ?: null;
                $match->update([
                    'referee' => $referee,
                    'referee_auto' => !($row['locked']['referee'] ?? false) && $this->autoFlag(
                        $referee, ($row['suggested_referee'] ?? null) ?: null,
                        $match->referee ?: null, (bool) $match->referee_auto,
                    ),
                    'scorer' => $scorer,
                    'scorer_auto' => !($row['locked']['scorer'] ?? false) && $this->autoFlag(
                        $scorer, ($row['suggested_scorer'] ?? null) ?: null,
                        $match->scorer ?: null, (bool) $match->scorer_auto,
                    ),
                ]);
            }
            // Without the venue in the range, applying one block would take
            // along the hall duties of the other venue on the same day.
            $range = fn ($query) => $query
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                ->when($singleVenue, fn ($q) => $venueId === null
                    ? $q->whereNull('venue_id')
                    : $q->where('venue_id', $venueId));
            // The duties are deleted and recreated. The origin of the old row
            // must come along, or every hall duty returns as automatic and
            // the lock loses its meaning.
            $oldDuties = $version->hallDuties()->with('team')->tap($range)->get()
                ->keyBy(fn (HallDuty $duty): string => DutySlots::hallDutyKey($duty->date->toDateString(), $duty->venue_id, $duty->start_time));
            $version->hallDuties()->tap($range)->delete();
            $teams = Team::query()->select(Team::PLANNER_COLUMNS)->get()->keyBy(fn (Team $team) => TeamCode::key($team->code));
            foreach ($draft['hall_duties'] ?? [] as $duty) {
                $team = $teams->get(TeamCode::key($duty['team_code'] ?? null));
                if (!$team) {
                    continue;
                }
                $old = $oldDuties->get(DutySlots::hallDutyKey($duty['date'], (int) $duty['venue_id'], (string) $duty['start']));
                HallDuty::create([
                    'version_id' => $version->id,
                    'date' => $duty['date'], 'start_time' => $duty['start'], 'end_time' => $duty['end'],
                    'venue_id' => $duty['venue_id'], 'team_id' => $team->id,
                    'team_auto' => !($duty['locked'] ?? false) && $this->autoFlag(
                        ($duty['team_code'] ?? null) ?: null,
                        ($duty['suggested_team'] ?? null) ?: null,
                        $old?->team?->code ?: null,
                        (bool) $old?->team_auto,
                    ),
                ]);
            }
            activity('planner-duties')->performedOn($version)->causedBy($userId)
                ->withProperties(['matches' => count($draft['matches'] ?? []), 'hall_duties' => count($draft['hall_duties'] ?? [])])
                ->log('Duty draft applied');
        });
    }

    /**
     * Checks a draft against the rules.
     *
     * @param  bool  $singleVenue  Distinguishes "all venues" from a deliberately chosen block without venue.
     * @return array{matches: array<int, array<string, list<string>>>, hall_duties: array<int, list<string>>, count: int}
     */
    public function validate(ScheduleVersion $version, array $draft, ?string $date = null, ?int $venueId = null, bool $singleVenue = false): array
    {
        $matches = $version->matches()->whereNotNull('date')->whereNotNull('time')->get();
        $byDate = DutySlots::byDate($matches);
        $byWeekday = $this->trainings()->groupBy('weekday');
        $teams = Team::query()->select(Team::PLANNER_COLUMNS)->get()->keyBy(fn (Team $team): string => TeamCode::key($team->code));
        $result = ['matches' => [], 'hall_duties' => [], 'count' => 0];
        $notFound = __('Het ingevulde team is niet gevonden.');

        foreach ($draft['matches'] ?? [] as $row) {
            $match = $matches->firstWhere('id', $row['id'] ?? null);
            if (!$match || ($date !== null && $match->date->toDateString() !== $date) || ($singleVenue && $match->venue_id !== $venueId)) {
                continue;
            }
            $slot = DutySlots::forMatch($match);
            foreach (['referee' => DutyType::Referee, 'scorer' => DutyType::Scorer] as $field => $duty) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value === '' || ($field === 'referee' && strcasecmp($value, self::NEVOBO) === 0)) {
                    continue;
                }
                $team = $teams->get(TeamCode::key($value));
                $reasons = $team
                    ? $this->rules->violations($team, $duty, $slot, $byDate[$slot['date']] ?? collect(), $byWeekday[Weekday::name($slot['date'])] ?? collect())
                    : [$notFound];
                if ($reasons) {
                    $result['matches'][$match->id][$field] = $reasons;
                    $result['count']++;
                }
            }
        }

        foreach ($draft['hall_duties'] ?? [] as $index => $duty) {
            if (($date !== null && ($duty['date'] ?? null) !== $date) || ($singleVenue && ($duty['venue_id'] ?? null) !== $venueId)) {
                continue;
            }
            // A duty without a team is not filled in yet, not filled in
            // wrongly; that should not block applying the rest.
            if (trim((string) ($duty['team_code'] ?? '')) === '') {
                continue;
            }

            $team = $teams->get(TeamCode::key((string) $duty['team_code']));
            $slot = ['date' => $duty['date'], 'start' => $duty['start'], 'end' => $duty['end'], 'venue_id' => $duty['venue_id']];
            $reasons = $team
                ? $this->rules->violations($team, DutyType::HallDuty, $slot, $byDate[$slot['date']] ?? collect(), $byWeekday[Weekday::name($slot['date'])] ?? collect())
                : [$notFound];
            if ($reasons) {
                $result['hall_duties'][$index] = $reasons;
                $result['count']++;
            }
        }

        return $result;
    }

    /**
     * The *_auto flag means "assigned by the generator", which is what
     * redistribute is allowed to overwrite. It is emphatically not "differs
     * from what was in the database" -- that would mark exactly the manual
     * corrections as reassignable and wipe them on the next run.
     */
    private function autoFlag(?string $chosen, ?string $suggestion, ?string $stored, bool $currentFlag): bool
    {
        if ($chosen !== $suggestion) {
            return false; // changed by hand in the draft
        }

        // The suggestion was taken: newly assigned by the generator, or left
        // alone -- then it stays as it once came about.
        return $suggestion === $stored ? $currentFlag : true;
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @param  array<int, array{referee: int, scorer: int, hall_duty: int, total: int, used: int}>  $tallies
     * @param  array<string, int>  $points
     * @param  array<string, int>  $taken
     */
    private function pick(Collection $teams, DutyType $duty, array $slot, Collection $matches, Collection $trainings, array &$tallies, array $points, array $taken, int $maxPerDay): ?string
    {
        $key = $this->tallyKey($duty);
        $team = $teams
            ->filter(fn (Team $team): bool => $this->rules->violations($team, $duty, $slot, $matches, $trainings) === []
                && !$this->avoidOwnMatch($team, $duty, $slot)
                && !$this->dayLimitReached($taken, $team->code, $maxPerDay))
            ->sortBy(function (Team $team) use ($duty, $slot, $matches, $trainings, $tallies, $key): array {
                $tally = $tallies[$team->id];
                $percentage = $tally['used'] > 0 ? ($tally['total'] / $tally['used']) * 100 : 999;

                return [
                    $duty === DutyType::Scorer ? $this->rules->scorerCategory($team) : 0,
                    $percentage >= 100 ? 1 : 0,
                    $percentage,
                    $tally['total'] - $tally['used'],
                    $this->rules->presenceScore($team, $slot, $matches, $trainings),
                    $tally[$key],
                    $team->code,
                ];
            })
            ->first();
        if (!$team) {
            return null;
        }

        $tally = $tallies[$team->id];
        $tally[$key]++;
        $tally['total'] += $points[$key];
        $tallies[$team->id] = $tally;

        return $team->code;
    }

    /** @return 'referee'|'scorer'|'hall_duty' */
    private function tallyKey(DutyType $duty): string
    {
        return match ($duty) {
            DutyType::Referee => 'referee',
            DutyType::Scorer => 'scorer',
            DutyType::HallDuty => 'hall_duty',
        };
    }

    /**
     * The points a duty earns. Without the volunteer points module every
     * duty counts as one, so the duties are still spread evenly.
     *
     * @return array{referee: int, scorer: int, hall_duty: int}
     */
    private function pointsPerDuty(PlannerSetting $settings): array
    {
        if (!$this->usesVolunteerPoints()) {
            return ['referee' => 1, 'scorer' => 1, 'hall_duty' => 1];
        }

        return [
            'referee' => (int) $settings->points_referee_duty,
            'scorer' => (int) $settings->points_scorer_duty,
            'hall_duty' => (int) $settings->points_hall_duty,
        ];
    }

    private function usesVolunteerPoints(): bool
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::VolunteerPoints);
    }

    /** @return Collection<int, Training> Empty without the trainings module. */
    private function trainings(): Collection
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Trainings)
            ? Training::query()->get()
            : collect();
    }

    /**
     * An empty or unknown assignment (or "Nevobo") belongs to no team and
     * does not count towards the day limit.
     *
     * @param  array<string, int>  $taken
     */
    private function bookDayDuty(array &$taken, ?string $teamCode): void
    {
        $key = TeamCode::key($teamCode);
        if ($key === '' || $key === TeamCode::key(self::NEVOBO)) {
            return;
        }

        $taken[$key] = ($taken[$key] ?? 0) + 1;
    }

    /** @param array<string, int> $taken */
    private function dayLimitReached(array $taken, ?string $teamCode, int $maxPerDay): bool
    {
        return $maxPerDay >= 1 && ($taken[TeamCode::key($teamCode)] ?? 0) >= $maxPerDay;
    }

    /**
     * Scoring your own match is allowed, but the generator does not hand it
     * out: a playing team has the best presence score and would almost always
     * get its own match. Teams that want that explicitly have the
     * scores_own_match flag.
     */
    private function avoidOwnMatch(Team $team, DutyType $duty, array $slot): bool
    {
        return $duty === DutyType::Scorer
            && !$team->scores_own_match
            && $this->rules->playsItself($team, $slot['match'] ?? null);
    }
}
