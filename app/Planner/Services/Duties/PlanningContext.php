<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamAbsence;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the planning rules need to know about one schedule, loaded
 * once: teams, matches, trainings, absences, the rounds and the register of
 * planned duties. The generator, the check and the team choice work on the
 * same context and therefore give the same answer.
 *
 * A duty is an array with the keys key, duty, date, start, end, venue_id,
 * round, points, block_id, role and match.
 */
final class PlanningContext
{
    public const DUTY_REFEREE = 'referee';

    public const DUTY_SCORER = 'scorer';

    public const DUTY_HALL = 'hall_duty';

    public const DUTY_VOLLEYSTARS = 'volleystars';

    public readonly DutyRegister $register;

    public readonly PlayingRounds $rounds;

    public readonly DutyRules $rules;

    /** @var array{referee: int, scorer: int, hall_duty: int, volleystars: int} */
    public readonly array $defaultPoints;

    /** @var Collection<string, Team> by team key */
    private Collection $teamsByCode;

    /** @var Collection<string, Collection<int, FixtureMatch>> */
    private Collection $matchesByDate;

    /** @var Collection<int, FixtureMatch> */
    private Collection $homeMatches;

    /** @var Collection<string, Collection<int, Training>> */
    private Collection $trainingsByWeekday;

    /** @var Collection<int, Collection<int, TeamAbsence>> */
    private Collection $absences;

    /** @var Collection<int, Venue> */
    private Collection $venues;

    /** @var array<string, Collection<int, FixtureMatch>> */
    private array $ownMatchesCache = [];

    /** @var array<int, array{total: float, remaining: array<string, int>, sum: int}> */
    private array $halves = [];

    /**
     * @param  Collection<int, Team>  $teams
     * @param  Collection<int, FixtureMatch>  $matches
     * @param  Collection<int, Training>  $trainings
     * @param  Collection<int, TeamAbsence>  $absences
     * @param  Collection<int, Venue>  $venues
     * @param  Collection<int, VolleystarsBlock>  $volleystarsBlocks
     * @param  Collection<int, HallDuty>|null  $hallDuties
     * @param  bool  $withPoints  Without the volunteer points module every duty counts as one and no team has a minimum.
     */
    public function __construct(
        public readonly PlannerSetting $settings,
        public readonly Collection $teams,
        Collection $matches,
        Collection $trainings,
        Collection $absences,
        Collection $venues,
        private readonly Collection $volleystarsBlocks,
        ?Collection $hallDuties = null,
        public readonly bool $withPoints = true,
        private readonly ?ClubTeamMatcher $matcher = null,
    ) {
        $this->rules = new DutyRules($settings->duty_rules);
        $this->defaultPoints = DutyPoints::defaults($settings);
        $this->teamsByCode = $teams->keyBy(fn (Team $team): string => TeamCode::key($team->code));
        $dated = $matches->filter(fn (FixtureMatch $match): bool => $match->date !== null && filled($match->time));
        $this->matchesByDate = $dated->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString());
        $this->homeMatches = $dated
            ->filter(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Home && !$match->is_sitting_volleyball)
            ->sortBy(fn (FixtureMatch $match): string => $match->date->toDateString().' '.$match->time)
            ->values();
        $this->trainingsByWeekday = $trainings->groupBy('weekday');
        $this->absences = $absences->groupBy('team_id');
        $this->venues = $venues->keyBy('id');
        $this->rounds = new PlayingRounds($this->homeMatches);
        $this->register = new DutyRegister;

        foreach ($this->homeMatches as $match) {
            foreach ([self::DUTY_REFEREE, self::DUTY_SCORER] as $duty) {
                if (filled($match->{$duty})) {
                    $this->register->put($this->dutyForMatch($match, $duty) + ['team' => (string) $match->{$duty}]);
                }
            }
        }
        // A hall duty always has a team: one without is not saved.
        foreach ($hallDuties ?? [] as $hallDuty) {
            $this->register->put($this->dutyForHallDuty(
                $hallDuty->date->toDateString(), $hallDuty->venue_id,
                substr((string) $hallDuty->start_time, 0, 5), substr((string) $hallDuty->end_time, 0, 5),
                $hallDuty->points,
            ) + ['team' => $hallDuty->team->code]);
        }
        foreach ($volleystarsBlocks as $block) {
            foreach ($block->tasks as $task) {
                if ($task->team) {
                    $this->register->put($this->dutyForVolleystars($block, $task->role, (int) $task->sort_order, $task->points) + ['team' => $task->team->code]);
                }
            }
        }
    }

    /** The context of a version of the current club. Trainings and VolleyStars only count with their module. */
    public static function forVersion(ScheduleVersion $version): self
    {
        $club = app(CurrentClub::class)->require();

        return new self(
            settings: PlannerSetting::current(),
            teams: Team::query()->select(Team::PLANNER_COLUMNS)->get(),
            matches: $version->matches()->with(['venue', 'court', 'homeTeam'])->whereNotNull('date')->whereNotNull('time')->orderBy('date')->orderBy('time')->get(),
            trainings: $club->hasFeature(PlannerFeature::Trainings) ? Training::query()->get() : collect(),
            absences: TeamAbsence::query()->get(),
            venues: Venue::query()->get(['id', 'venue_code', 'city', 'name']),
            volleystarsBlocks: $club->hasFeature(PlannerFeature::Volleystars)
                ? $version->volleystarsBlocks()->with(['tasks.team', 'venue'])->get()
                : collect(),
            hallDuties: $version->hallDuties()->with('team')->get(),
            withPoints: $club->hasFeature(PlannerFeature::VolunteerPoints),
            matcher: ClubTeamMatcher::forCurrentClub(),
        );
    }

    /**
     * Lays the assignments of a draft over those from the database. That way
     * the check and the team choice work with what is on the screen.
     */
    public function applyDraft(array $draft): void
    {
        $matches = $this->homeMatches->keyBy('id');
        foreach ($draft['matches'] ?? [] as $row) {
            $match = $matches->get($row['id'] ?? null);
            if (!$match) {
                continue;
            }
            foreach ([self::DUTY_REFEREE, self::DUTY_SCORER] as $duty) {
                $this->register->put($this->dutyForMatch($match, $duty) + ['team' => (string) ($row[$duty] ?? '')]);
            }
        }
        foreach ($draft['hall_duties'] ?? [] as $row) {
            $date = (string) ($row['date'] ?? '');
            $venueId = isset($row['venue_id']) ? (int) $row['venue_id'] : null;
            $start = (string) ($row['start'] ?? '');
            $duty = $this->dutyForHallDuty(
                $date, $venueId, $start, (string) ($row['end'] ?? ''),
                $this->register->get($this->hallDutyKey($date, $venueId, $start))['stored_points'] ?? null,
            );
            $this->register->put($duty + ['team' => (string) ($row['team_code'] ?? '')]);
        }
    }

    // ------------------------------------------------------------------ duties

    /** @return Collection<int, FixtureMatch> */
    public function homeMatches(): Collection
    {
        return $this->homeMatches;
    }

    /** @return Collection<int, VolleystarsBlock> */
    public function volleystarsBlocks(): Collection
    {
        return $this->volleystarsBlocks;
    }

    public function dutyForMatch(FixtureMatch $match, string $duty): array
    {
        $start = substr((string) $match->time, 0, 5);
        $stored = $duty === self::DUTY_REFEREE ? $match->referee_points : $match->scorer_points;

        return [
            'key' => 'm:'.$match->id.':'.$duty,
            'duty' => $duty,
            'role' => null,
            'date' => $match->date->toDateString(),
            'start' => $start,
            'end' => PlayingRounds::time(PlayingRounds::minutes($start) + (int) $match->duration_minutes),
            'venue_id' => $match->venue_id,
            'round' => $this->rounds->ofMatch($match->id),
            'points' => $this->points($stored, $duty),
            'stored_points' => $stored,
            'block_id' => null,
            'match' => $match,
        ];
    }

    public function dutyForHallDuty(string $date, ?int $venueId, string $start, string $end, ?int $points = null): array
    {
        return [
            'key' => $this->hallDutyKey($date, $venueId, $start),
            'duty' => self::DUTY_HALL,
            'role' => null,
            'date' => $date,
            'start' => substr($start, 0, 5),
            'end' => substr($end, 0, 5),
            'venue_id' => $venueId,
            'round' => $this->rounds->at($date, $venueId, $start),
            'points' => $this->points($points, self::DUTY_HALL),
            'stored_points' => $points,
            'block_id' => null,
            'match' => null,
        ];
    }

    public function hallDutyKey(string $date, mixed $venueId, string $start): string
    {
        return 'h:'.$date.'|'.$venueId.'|'.substr($start, 0, 5);
    }

    public function dutyForVolleystars(VolleystarsBlock $block, VolleystarsRole $role, int $sortOrder, ?int $points = null): array
    {
        [$start, $end] = $role->times($block);
        $date = $block->date->toDateString();

        return [
            'key' => 'v:'.$block->id.':'.$role->value.':'.$sortOrder,
            'duty' => self::DUTY_VOLLEYSTARS,
            'role' => $role,
            'date' => $date,
            'start' => substr((string) $start, 0, 5),
            'end' => substr((string) $end, 0, 5),
            'venue_id' => $block->venue_id,
            'round' => $this->rounds->at($date, $block->venue_id, (string) $start),
            'points' => $this->points($points, self::DUTY_VOLLEYSTARS),
            'stored_points' => $points,
            'block_id' => $block->id,
            'match' => null,
        ];
    }

    /** What a duty counts: its own points, the default for a new one, or one without the points module. */
    private function points(?int $stored, string $duty): int
    {
        return $this->withPoints ? ($stored ?? $this->defaultPoints[$duty]) : 1;
    }

    // ------------------------------------------------------------------- teams

    public function teamWithCode(?string $code): ?Team
    {
        return $this->teamsByCode->get(TeamCode::key($code));
    }

    /**
     * The teams the duty planning may plan.
     *
     * @return Collection<int, Team>
     */
    public function plannableTeams(): Collection
    {
        return $this->teams->filter(fn (Team $team): bool => (bool) $team->include_in_duties)->values();
    }

    public function balance(Team $team): PointsBalance
    {
        return PointsBalance::forTeam($team, $this->register->points($team->code), $this->withPoints);
    }

    /**
     * The standing as if this duty were still open. In the team choice the
     * current team is already on the duty; without this correction it looked
     * further along than the other candidates.
     */
    public function balanceWithout(Team $team, string $key): PointsBalance
    {
        $duty = $this->register->get($key);
        $own = $duty !== null && $duty['team'] === TeamCode::key($team->code) ? $duty['points'] : 0;

        return PointsBalance::forTeam($team, $this->register->points($team->code) - $own, $this->withPoints);
    }

    /**
     * The matches the team itself plays that day. Every duty that day asks
     * the same question, so the answer is cached per team and day.
     *
     * @return Collection<int, FixtureMatch>
     */
    public function ownMatches(Team $team, string $date): Collection
    {
        return $this->ownMatchesCache[$team->id.'|'.$team->code.'|'.$date] ??= ($this->matchesByDate[$date] ?? collect())
            ->filter(fn (FixtureMatch $match): bool => $this->playsItself($team, $match))
            ->values();
    }

    public function playsItself(Team $team, ?FixtureMatch $match): bool
    {
        return $match !== null
            && ($this->isTeamOnSide($team, $match->home_team_name, $match->home_team_id)
                || $this->isTeamOnSide($team, $match->away_team_name, $match->away_team_id));
    }

    /** The own team code ("HS2") of the home side of this match, or ''. */
    public function homeCode(?FixtureMatch $match): string
    {
        return $match === null ? '' : (string) $this->matcher()->ownTeamCode((string) $match->home_team_name);
    }

    /**
     * A recognisable club name takes precedence over an old, wrong team id.
     * Pool feeds of HS 7 also contain matches of HS 8 and used to link them
     * to HS 7.
     */
    private function isTeamOnSide(Team $team, ?string $name, ?int $teamId): bool
    {
        if ($this->matcher()->isOwnTeam($name)) {
            return $this->matcher()->isOwnTeamWithCode($name, $team->code);
        }

        return $team->id === $teamId;
    }

    private function matcher(): ClubTeamMatcher
    {
        return $this->matcher ?? ClubTeamMatcher::forCurrentClub();
    }

    /** @return Collection<int, Training> */
    public function trainingsOf(Team $team, string $date): Collection
    {
        return ($this->trainingsByWeekday[Weekday::name($date)] ?? collect())->where('team_id', $team->id);
    }

    /** @return Collection<int, TeamAbsence> */
    public function absencesOf(Team $team): Collection
    {
        return $this->absences[$team->id] ?? collect();
    }

    // ------------------------------------------------------------------ venues

    /** Two venues with the same venue code are the same venue. */
    public function sameVenue(?int $a, ?int $b): bool
    {
        if ($a === null || $b === null) {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        $codeA = $this->venues->get($a)?->venue_code;

        return filled($codeA) && $codeA === $this->venues->get($b)?->venue_code;
    }

    /** The city an away match counts as at home in: the configured one, otherwise that of the main venue. */
    public function homeCity(): string
    {
        return $this->rules->homeCity()
            ?: trim((string) $this->venues->get($this->settings->main_venue_id)?->city);
    }

    /**
     * An away match in a venue in the home city counts as a match in the own
     * hall: the team is not away all day.
     */
    public function isInHomeCity(FixtureMatch $match): bool
    {
        $city = $match->venue_id ? $this->venues->get($match->venue_id)?->city : null;
        $home = $this->homeCity();

        return filled($city) && $home !== '' && strcasecmp(trim((string) $city), $home) === 0;
    }

    // -------------------------------------------------------------- fair share

    /**
     * How many points the team is behind on its fair share in the season half
     * of this date. Null when no halves are set or the team has no minimum.
     */
    public function arrears(Team $team, string $date, ?string $except = null): ?float
    {
        $half = $this->settings->seasonHalf($date);
        if ($half === null || !$team->include_in_duties || !$this->balance($team)->hasMinimum()) {
            return null;
        }

        [$from, $to] = $this->settings->halfPeriod($half);

        return $this->fairShare($team, $half) - $this->register->pointsBetween($team->code, $from, $to, $except);
    }

    /** Fair share = points in the half x what this team still has to fill / what all teams together still have to fill. */
    public function fairShare(Team $team, int $half): float
    {
        $split = $this->halves[$half] ??= $this->splitOfHalf($half);
        $remaining = $split['remaining'][TeamCode::key($team->code)] ?? 0;

        return $split['sum'] > 0 ? $split['total'] * $remaining / $split['sum'] : 0.0;
    }

    /** @return array{total: float, remaining: array<string, int>, sum: int} */
    private function splitOfHalf(int $half): array
    {
        [$from, $to] = $this->settings->halfPeriod($half);
        $remaining = [];
        foreach ($this->plannableTeams() as $team) {
            $balance = PointsBalance::forTeam($team, $this->register->pointsBefore($team->code, $from), $this->withPoints);
            if ($balance->hasMinimum()) {
                $remaining[TeamCode::key($team->code)] = $balance->remaining();
            }
        }

        return ['total' => (float) $this->pointsInPeriod($from, $to), 'remaining' => $remaining, 'sum' => array_sum($remaining)];
    }

    /** The points of all duties in a period, taken or still open. */
    public function pointsInPeriod(string $from, string $to): int
    {
        $points = 0;
        foreach ($this->homeMatches as $match) {
            $date = $match->date->toDateString();
            if ($date < $from || $date > $to) {
                continue;
            }
            if (!$this->federationProvidesReferee($match)) {
                $points += $this->points($match->referee_points, self::DUTY_REFEREE);
            }
            $points += $this->points($match->scorer_points, self::DUTY_SCORER);
        }
        foreach ($this->rounds->all() as $round) {
            $slot = $round['slot'];
            if ($slot['date'] >= $from && $slot['date'] <= $to && $this->settings->hasHallDutyOnDate($slot['date'])) {
                $points += $this->register->get($this->hallDutyKey($slot['date'], $slot['venue_id'], $slot['start']))['points']
                    ?? $this->points(null, self::DUTY_HALL);
            }
        }
        foreach ($this->volleystarsBlocks as $block) {
            $date = $block->date->toDateString();
            if ($date >= $from && $date <= $to) {
                $points += $block->tasks->sum(fn (VolleystarsTask $task): int => $this->points($task->points, self::DUTY_VOLLEYSTARS));
            }
        }

        return $points;
    }

    public function federationProvidesReferee(FixtureMatch $match): bool
    {
        return (bool) $match->nevobo_referee || (bool) $match->homeTeam?->provides_referee;
    }

    /** The number of days between two dates. */
    public static function daysBetween(string $a, string $b): int
    {
        return (int) abs(CarbonImmutable::parse($a)->diffInDays(CarbonImmutable::parse($b)));
    }
}
