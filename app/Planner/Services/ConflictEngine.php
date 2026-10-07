<?php

namespace App\Planner\Services;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Checks a placement of a match against venue availability, other matches on
 * the same court, the changeover buffer, trainings, hall activities and the
 * team's other matches.
 *
 * Every finding is an array with the keys level (LEVEL_*), type (TYPE_*),
 * message (translated text) and optionally match_id (the other match).
 */
class ConflictEngine
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CONFLICT = 'conflict';

    public const TYPE_AVAILABILITY = 'availability';

    public const TYPE_COURT_OVERLAP = 'court_overlap';

    public const TYPE_BUFFER = 'buffer';

    public const TYPE_TRAINING = 'training';

    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_TEAM = 'team';

    /** Added by AvailabilityChecker: a Nevobo source could not be read. */
    public const TYPE_EXTERNAL_SOURCE = 'external_source';

    /** Added by AvailabilityChecker: the team plays an external match that day. */
    public const TYPE_EXTERNAL_TEAM = 'external_team';

    /** @var array<string, list<string>> */
    private array $detectionCache = [];

    /** The team list the detection cache belongs to; holding a reference keeps it valid. */
    private ?Collection $detectionTeams = null;

    public function __construct(private readonly ConflictDataLoader $data) {}

    /**
     * Without start and end time a rule applies to the whole day, as always.
     * When the caller passes a window, a rule with times only counts when the
     * two touch -- and an "available" rule with times is the rental: outside
     * that window the venue cannot be used.
     *
     * @param  Collection<int, Availability>  $rules
     * @return array{status: AvailabilityStatus, rule: ?Availability}
     */
    public function resolveAvailability(
        Collection $rules,
        int $venueId,
        int $courtId,
        string $date,
        ?string $start = null,
        ?string $end = null,
    ): array {
        $applicable = $rules
            ->filter(fn (Availability $rule): bool => $rule->venue_id === $venueId
                && $this->ruleApplies($rule, $date));

        foreach ([null, $courtId] as $level) {
            $rule = $applicable
                ->when($level === null,
                    fn (Collection $r): Collection => $r->whereNull('court_id'),
                    fn (Collection $r): Collection => $r->where('court_id', $level))
                ->sortByDesc(fn (Availability $r) => $this->scopeScore($r))
                ->first();

            if (!$rule instanceof Availability) {
                continue;
            }

            $status = $this->statusWithinWindow($rule, $start, $end);
            if ($status === null) {
                continue;
            }

            // A closed venue cascades to all its courts; at court level the
            // rule found always counts.
            if ($level !== null || $status === AvailabilityStatus::Closed) {
                return ['status' => $status, 'rule' => $rule];
            }
        }

        return ['status' => AvailabilityStatus::Available, 'rule' => null];
    }

    /**
     * The status this rule gives for the requested time window, or null when
     * the rule says nothing about it.
     */
    private function statusWithinWindow(Availability $rule, ?string $start, ?string $end): ?AvailabilityStatus
    {
        if (!$rule->hasTimeWindow() || $start === null || $end === null) {
            return $rule->status;
        }

        $from = $this->minutes($start);
        $until = $this->minutes($end);
        $windowFrom = $this->minutes((string) $rule->start_time);
        $windowUntil = $this->minutes((string) $rule->end_time);
        $touches = $from < $windowUntil && $windowFrom < $until;

        if ($rule->status === AvailabilityStatus::Available) {
            // The rental: anything not entirely within the window is impossible.
            return $from >= $windowFrom && $until <= $windowUntil
                ? AvailabilityStatus::Available
                : AvailabilityStatus::Closed;
        }

        return $touches ? $rule->status : null;
    }

    /**
     * The codes of the club's teams in a match name. A version scan asks this
     * over 24,000 times for a few hundred different names, and every question
     * cost a regex per team. The answer depends only on the name and the team
     * list, so the scan remembers it; another team list starts a new cache.
     *
     * @param  Collection<int, Team>  $teams
     * @return list<string>
     */
    public function detectTeams(string $name, Collection $teams): array
    {
        if ($this->detectionTeams !== $teams) {
            $this->detectionTeams = $teams;
            $this->detectionCache = [];
        }
        if (array_key_exists($name, $this->detectionCache)) {
            return $this->detectionCache[$name];
        }

        if (!ClubTeamMatcher::forCurrentClub()->isOwnTeam($name)) {
            return $this->detectionCache[$name] = [];
        }

        return $this->detectionCache[$name] = $teams
            ->filter(fn (Team $team): bool => $this->nameContainsCode($name, $team->code))
            ->pluck('code')->values()->all();
    }

    /**
     * The same team is "HS 1" in one source and "HS1" or "hs-1" in another;
     * TeamCode::pattern() knows those spellings and guards the boundaries.
     */
    private function nameContainsCode(string $name, ?string $code): bool
    {
        $pattern = TeamCode::pattern($code);

        return $pattern !== null && preg_match($pattern, $name) === 1;
    }

    /**
     * @param  array<string, mixed>  $placement  date, time, duration_minutes (optional), venue_id, court_id, second_court_id (optional)
     * @param  Collection<int, FixtureMatch>|null  $matches  the matches of that day; loaded (with provisional moves) when null
     * @param  Collection<int, Availability>|null  $rules  availability rules; all of the club's rules may be passed
     * @param  Collection<int, Team>|null  $teams
     * @return list<array{level: string, type: string, message: string, match_id?: int}>
     */
    public function checkPlacement(
        FixtureMatch $match,
        array $placement,
        ?Collection $matches = null,
        ?Collection $rules = null,
        ?Collection $teams = null,
    ): array {
        $date = CarbonImmutable::parse($placement['date'])->toDateString();
        $time = substr((string) $placement['time'], 0, 5);
        $venueId = (int) $placement['venue_id'];
        $courtIds = array_values(array_filter([(int) $placement['court_id'], (int) ($placement['second_court_id'] ?? 0)]));
        $duration = (int) ($placement['duration_minutes'] ?? $match->duration_minutes);
        // A proposal to this day keeps its court occupied, even though the
        // match itself is still at its old place. Otherwise someone could put
        // something else there while the opponent still decides on the spot.
        $matches ??= FixtureMatch::query()->where('version_id', $match->version_id)->whereDate('date', $date)->get()
            ->concat(app(ProvisionalMove::class)->onDate((int) $match->version_id, $date));
        // Preloaded collections are not optional for performance: without
        // these two a version scan fetched them again per match.
        // resolveAvailability filters on venue itself, so passing all of the
        // club's rules is fine.
        $rules ??= Availability::query()->where('venue_id', $venueId)->get();
        $teams ??= $this->data->teams((int) $match->club_id);
        $findings = [];

        // Pass the match time, so a rental window or a closure of a few hours
        // only counts for matches that actually fall within it.
        $endTime = CarbonImmutable::createFromFormat('H:i', $time)->addMinutes($duration)->format('H:i');

        foreach ($courtIds as $courtId) {
            $outcome = $this->resolveAvailability($rules, $venueId, $courtId, $date, $time, $endTime);
            $window = $outcome['rule']?->hasTimeWindow() ? ' ('.$outcome['rule']->window().')' : '';

            if ($outcome['status'] === AvailabilityStatus::Closed) {
                $findings[] = ['level' => self::LEVEL_CONFLICT, 'type' => self::TYPE_AVAILABILITY, 'message' => __('Het veld is gesloten').$window.'.'];
            } elseif ($outcome['status'] === AvailabilityStatus::Partial) {
                $findings[] = ['level' => self::LEVEL_WARNING, 'type' => self::TYPE_AVAILABILITY, 'message' => __('Het veld is gedeeltelijk beschikbaar').$window.'.'];
            }
        }

        $buffer = max(0, (int) $this->data->settings((int) $match->club_id)->buffer_minutes);
        foreach ($matches as $other) {
            if ($other->id === $match->id || !$other->time || $this->isSameMatch($match, $other)) {
                continue;
            }
            $otherCourts = array_filter([$other->court_id, $other->second_court_id]);
            if ($other->venue_id !== $venueId || !array_intersect($courtIds, $otherCourts)) {
                continue;
            }
            if ($this->overlaps($time, $duration, $other->time, $other->duration_minutes)) {
                $findings[] = ['level' => self::LEVEL_CONFLICT, 'type' => self::TYPE_COURT_OVERLAP, 'message' => __('Het veld is al bezet.'), 'match_id' => $other->id];

                continue;
            }
            // The configured buffer is changeover time: hall empty, new teams in.
            $gap = $this->gapInMinutes($time, $duration, $other->time, $other->duration_minutes);
            if ($buffer > 0 && $gap < $buffer) {
                $findings[] = [
                    'level' => self::LEVEL_WARNING, 'type' => self::TYPE_BUFFER,
                    'message' => __('Er zit :gap minuten tussen deze wedstrijd en de vorige of volgende; ingesteld is :buffer.', ['gap' => $gap, 'buffer' => $buffer]),
                    'match_id' => $other->id,
                ];
            }
        }

        foreach ($this->data->trainingsOn((int) $match->club_id, $date) as $training) {
            if ((int) $training->venue_id !== $venueId || !$training->start_time || !$training->end_time) {
                continue;
            }
            $trainingDuration = $this->minutes($training->end_time) - $this->minutes($training->start_time);
            if ($trainingDuration > 0 && $this->overlaps($time, $duration, $training->start_time, $trainingDuration)) {
                $findings[] = [
                    'level' => self::LEVEL_WARNING, 'type' => self::TYPE_TRAINING,
                    'message' => $training->team?->code
                        ? __('Er staat een training van :team in deze hal op dit tijdstip.', ['team' => $training->team->code])
                        : __('Er staat een training in deze hal op dit tijdstip.'),
                ];
            }
        }

        foreach ($this->data->activitiesOn($match->version_id, $date) as $activity) {
            if ((int) $activity->venue_id !== $venueId || !$activity->start_time) {
                continue;
            }
            if ($activity->court_id && !in_array((int) $activity->court_id, $courtIds, true)) {
                continue;
            }
            $activityDuration = $activity->end_time
                ? $this->minutes($activity->end_time) - $this->minutes($activity->start_time)
                : 60;
            if ($activityDuration > 0 && $this->overlaps($time, $duration, $activity->start_time, $activityDuration)) {
                $findings[] = [
                    'level' => self::LEVEL_WARNING, 'type' => self::TYPE_ACTIVITY,
                    'message' => __('Er staat een zaalactiviteit gepland: :title.', ['title' => $activity->title ?: __('zonder titel')]),
                ];
            }
        }

        $involved = collect($this->detectTeams((string) $match->home_team_name, $teams))
            ->merge($this->detectTeams((string) $match->away_team_name, $teams))->unique();
        foreach ($matches as $other) {
            if ($other->id === $match->id || !$other->time || $this->isSameMatch($match, $other)) {
                continue;
            }
            $otherTeams = collect($this->detectTeams((string) $other->home_team_name, $teams))
                ->merge($this->detectTeams((string) $other->away_team_name, $teams));
            foreach ($involved->intersect($otherTeams) as $code) {
                $overlap = $this->overlaps($time, $duration, $other->time, $other->duration_minutes);
                $findings[] = [
                    'level' => $overlap ? self::LEVEL_CONFLICT : self::LEVEL_WARNING,
                    'type' => self::TYPE_TEAM,
                    'message' => $overlap
                        ? __('Team :code heeft een overlappende wedstrijd.', ['code' => $code])
                        : __('Team :code speelt al op deze dag.', ['code' => $code]),
                    'match_id' => $other->id,
                ];
            }
        }

        return $findings;
    }

    /**
     * Schedule conflicts of one team, apart from a venue: does that team play
     * elsewhere at that moment, or does it train? For an away match that is
     * the only sensible question -- there is no court of ours to give away,
     * so no court overlap or availability to check.
     *
     * @param  string|null  $teamName  the team to check; defaults to the away team name
     * @param  Collection<int, FixtureMatch>|null  $matches  preloaded matches of the version (any dates)
     * @return list<array{level: string, type: string, message: string, match_id?: int}>
     */
    public function teamSchedule(
        FixtureMatch $match,
        string $date,
        string $time,
        int $duration,
        ?string $teamName = null,
        ?Collection $matches = null,
    ): array {
        $date = CarbonImmutable::parse($date)->toDateString();
        $time = substr($time, 0, 5);
        $teams = $this->data->teams((int) $match->club_id);
        $codes = collect($this->detectTeams($teamName ?? (string) $match->away_team_name, $teams));
        if ($codes->isEmpty()) {
            return [];
        }

        $findings = [];
        // A list view asks this per row; with a preloaded collection that
        // stays one query instead of one per row.
        $others = $matches
            ? $matches->filter(fn (FixtureMatch $other): bool => $other->getKey() !== $match->getKey()
                && $other->date?->toDateString() === $date)
            : FixtureMatch::query()->where('version_id', $match->version_id)
                ->whereKeyNot($match->id)->whereDate('date', $date)->get();
        foreach ($others as $other) {
            if (!$other->time || $this->isSameMatch($match, $other)) {
                continue;
            }
            $otherTeams = collect($this->detectTeams((string) $other->home_team_name, $teams))
                ->merge($this->detectTeams((string) $other->away_team_name, $teams));
            foreach ($codes->intersect($otherTeams) as $code) {
                if ($this->overlaps($time, $duration, $other->time, $other->duration_minutes)) {
                    $findings[] = [
                        'level' => self::LEVEL_CONFLICT, 'type' => self::TYPE_TEAM,
                        'message' => __(':code speelt ook :home – :away om :time.', [
                            'code' => $code, 'home' => $other->home_team_name, 'away' => $other->away_team_name,
                            'time' => substr((string) $other->time, 0, 5),
                        ]),
                        'match_id' => $other->id,
                    ];
                }
            }
        }

        $teamIds = $teams->filter(fn (Team $team): bool => $codes->contains($team->code))->pluck('id');
        foreach ($this->data->trainingsOn((int) $match->club_id, $date) as $training) {
            if (!$teamIds->contains($training->team_id) || !$training->start_time || !$training->end_time) {
                continue;
            }
            $trainingDuration = $this->minutes($training->end_time) - $this->minutes($training->start_time);
            if ($trainingDuration > 0 && $this->overlaps($time, $duration, $training->start_time, $trainingDuration)) {
                $findings[] = [
                    'level' => self::LEVEL_CONFLICT, 'type' => self::TYPE_TRAINING,
                    'message' => __(':team heeft training van :start tot :end.', [
                        'team' => $training->team?->code ?? __('Het team'),
                        'start' => substr((string) $training->start_time, 0, 5),
                        'end' => substr((string) $training->end_time, 0, 5),
                    ]),
                ];
            }
        }

        return collect($findings)->unique(fn (array $f): string => $f['type'].'|'.$f['message'])->values()->all();
    }

    /**
     * Every finding carries the match the scan was on: match_id is the other
     * party, subject_match_id the cause. Without that second reference an
     * overview fell back on the text alone -- and the scan deduplicated two
     * closed courts on different days into one line "Het veld is gesloten",
     * without anyone being able to see where.
     *
     * @return list<array{level: string, type: string, message: string, match_id?: int, subject_match_id: int}>
     */
    public function scanVersion(ScheduleVersion $version): array
    {
        $matches = $version->matches()->whereNotNull('date')->whereNotNull('time')->get();
        $rules = Availability::query()->get();
        $teams = Team::query()->get();
        // One grouping by date instead of filtering the whole list per match:
        // the latter compared Carbon objects and took two thirds of the scan
        // time on 621 matches.
        $perDay = $matches->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString());

        return $matches->flatMap(fn (FixtureMatch $match) => array_map(
            fn (array $finding): array => $finding + ['subject_match_id' => $match->getKey()],
            $this->checkPlacement($match, [
                'date' => $match->date->toDateString(), 'time' => $match->time,
                'duration_minutes' => $match->duration_minutes, 'venue_id' => $match->venue_id,
                'court_id' => $match->court_id, 'second_court_id' => $match->second_court_id,
            ], $perDay->get($match->date->toDateString()), $rules, $teams),
        ))->unique(fn (array $f): string => implode(':', [
            $f['subject_match_id'], $f['type'], $f['match_id'] ?? 0, $f['message'],
        ]))->values()->all();
    }

    /**
     * Whether this referee is already on another match of the version within
     * two hours of this time. "Nevobo" (federation referee) never conflicts.
     */
    public function officialsConflict(FixtureMatch $match, ?string $official, string $date, string $time, ?int $venueId): bool
    {
        if (!$official || mb_strtolower(trim($official)) === 'nevobo') {
            return false;
        }

        return FixtureMatch::query()->where('version_id', $match->version_id)->whereKeyNot($match->id)
            ->whereDate('date', $date)->where('referee', $official)->get()
            ->contains(function (FixtureMatch $other) use ($time): bool {
                $distance = abs($this->minutes($time) - $this->minutes($other->time));

                return $distance < 120;
            });
    }

    /** Does this availability rule apply on the given date? */
    public function ruleAppliesOn(Availability $rule, string $date): bool
    {
        return $this->ruleApplies($rule, $date);
    }

    /** How heavy is this rule's scope? A date rule beats a period, which beats a weekday. */
    public function scopeWeight(Availability $rule): int
    {
        return $this->scopeScore($rule);
    }

    private function ruleApplies(Availability $rule, string $date): bool
    {
        $day = CarbonImmutable::parse($date);

        return match ($rule->scope) {
            AvailabilityScope::Date => $rule->date?->toDateString() === $day->toDateString(),
            AvailabilityScope::Weekday => $rule->weekday === Weekday::number($day),
            AvailabilityScope::Period => $rule->period_start && $rule->period_end
                && $day->betweenIncluded($rule->period_start, $rule->period_end),
            default => false,
        };
    }

    private function scopeScore(Availability $rule): int
    {
        return match ($rule->scope) {
            AvailabilityScope::Date => 30,
            AvailabilityScope::Period => 20,
            AvailabilityScope::Weekday => 10,
            default => 0,
        };
    }

    /** Minutes between two non-overlapping blocks. */
    private function gapInMinutes(string $startA, int $durationA, string $startB, int $durationB): int
    {
        $a = $this->minutes($startA);
        $b = $this->minutes($startB);

        return max(0, max($a, $b) - min($a + $durationA, $b + $durationB));
    }

    private function overlaps(string $startA, int $durationA, string $startB, int $durationB): bool
    {
        $a = $this->minutes($startA);
        $b = $this->minutes($startB);

        return $a < $b + $durationB && $b < $a + $durationA;
    }

    private function isSameMatch(FixtureMatch $match, FixtureMatch $other): bool
    {
        $normalize = fn (?string $code): string => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($code ?? ''));
        $code = $normalize($match->match_code);
        if ($code !== '' && $code === $normalize($other->match_code)) {
            return true;
        }
        $team = fn (?string $name): string => preg_replace('/\s+/', ' ', mb_strtoupper(trim($name ?? '')));

        return $team($match->home_team_name) !== ''
            && $team($match->home_team_name) === $team($other->home_team_name)
            && $team($match->away_team_name) === $team($other->away_team_name);
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
