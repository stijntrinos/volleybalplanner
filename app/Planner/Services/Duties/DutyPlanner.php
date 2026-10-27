<?php

namespace App\Planner\Services\Duties;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Support\TeamCode;
use Carbon\CarbonImmutable;
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

    public function __construct(
        private readonly RuleEngine $rules,
        private readonly TeamChoice $teamChoice,
    ) {}

    /**
     * @return array{
     *     matches: list<array{id: int, date: string, time: string, venue_id: ?int, code: ?string, venue: ?string, court: ?string, home: ?string, away: ?string, referee: ?string, scorer: ?string, suggested_referee: ?string, suggested_scorer: ?string, locked: array{referee: bool, scorer: bool}}>,
     *     hall_duties: list<array{date: string, venue_id: int, venue: ?string, start: string, end: string, round: int, team_code: ?string, suggested_team: ?string, locked: bool}>
     * }
     */
    public function generate(ScheduleVersion $version, string $mode = self::MODE_FILL, ?string $from = null, ?string $to = null, ?int $venueId = null): array
    {
        $context = PlanningContext::forVersion($version);
        $within = fn (string $date, ?int $venue): bool => (!$from || $date >= $from)
            && (!$to || $date <= $to)
            && (!$venueId || $venue === $venueId);
        $home = $context->homeMatches()
            ->filter(fn (FixtureMatch $match): bool => $within($match->date->toDateString(), $match->venue_id))
            ->values();

        // A playing day in the past does not need filling: what is there, was
        // played.
        $today = CarbonImmutable::now()->toDateString();

        // First take everything that is recalculated out of the register.
        // Otherwise the old assignment counts towards the standing and the
        // weekly maximum of the team that happened to have it, while the duty
        // is open again.
        $recalculate = [];
        foreach ($home as $match) {
            $past = $match->date->toDateString() < $today;
            $recalculate[$match->id] = [
                'referee' => !$past && (!$match->referee || ($mode === self::MODE_REDISTRIBUTE && $match->referee_auto)),
                'scorer' => !$past && (!$match->scorer || ($mode === self::MODE_REDISTRIBUTE && $match->scorer_auto)),
            ];
            foreach ([PlanningContext::DUTY_REFEREE, PlanningContext::DUTY_SCORER] as $duty) {
                if ($recalculate[$match->id][$duty]) {
                    $context->register->remove($context->dutyForMatch($match, $duty)['key']);
                }
            }
        }

        $existingDuties = $version->hallDuties()->with('team')->get()
            ->keyBy(fn (HallDuty $duty): string => $context->hallDutyKey($duty->date->toDateString(), $duty->venue_id, (string) $duty->start_time));
        $rounds = [];
        foreach ($context->rounds->all() as $round) {
            $slot = $round['slot'];
            // A hall duty belongs to a venue and a hall duty day.
            if (!$within($slot['date'], $slot['venue_id']) || !$context->settings->hasHallDutyOnDate($slot['date'])) {
                continue;
            }
            $existing = $existingDuties->get($context->hallDutyKey($slot['date'], $slot['venue_id'], $slot['start']));
            $existingTeam = $existing?->team?->code;
            $past = $slot['date'] < $today;
            // A played day without duty does not get one anymore.
            if ($past && blank($existingTeam)) {
                continue;
            }
            // Only what the generator assigned itself may be redistributed.
            $again = !$past && (blank($existingTeam) || ($mode === self::MODE_REDISTRIBUTE && $existing->team_auto));
            $duty = $context->dutyForHallDuty($slot['date'], $slot['venue_id'], $slot['start'], $slot['end'], $existing?->points);
            if ($again) {
                $context->register->remove($duty['key']);
            }
            $rounds[] = ['slot' => $slot, 'existing' => $existing, 'existingTeam' => $existingTeam, 'again' => $again, 'duty' => $duty];
        }

        $result = ['matches' => [], 'hall_duties' => []];
        foreach ($home as $match) {
            $referee = $match->referee;
            $scorer = $match->scorer;
            $refereeDuty = $context->dutyForMatch($match, PlanningContext::DUTY_REFEREE);
            $scorerDuty = $context->dutyForMatch($match, PlanningContext::DUTY_SCORER);

            if ($recalculate[$match->id]['referee']) {
                $referee = $context->federationProvidesReferee($match)
                    ? self::NEVOBO
                    : $this->teamChoice->best($refereeDuty, $context)?->code;
                // Book right away and not after the scorer: otherwise both
                // choices see the same standing and one team gets two duties
                // from one match.
                $context->register->put($refereeDuty + ['team' => (string) $referee]);
            }
            if ($recalculate[$match->id]['scorer']) {
                $scorer = $match->homeTeam?->scoresOwnMatchOn($refereeDuty['date'])
                    ? $match->homeTeam->code
                    : $this->teamChoice->best($scorerDuty, $context)?->code;
                $context->register->put($scorerDuty + ['team' => (string) $scorer]);
            }

            $result['matches'][] = [
                'id' => $match->id, 'date' => $refereeDuty['date'], 'time' => $refereeDuty['start'],
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

        foreach ($rounds as $round) {
            $team = $round['again']
                ? $this->teamChoice->best($round['duty'], $context)?->code
                : $round['existingTeam'];
            if ($round['again']) {
                $context->register->put($round['duty'] + ['team' => (string) $team]);
            }
            $result['hall_duties'][] = $round['slot'] + [
                'team_code' => $team,
                'suggested_team' => $team,
                'locked' => filled($round['existingTeam']) && !$round['existing']->team_auto,
            ];
        }

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
            // The duties are deleted and recreated. The origin and the points
            // of the old row must come along, or every hall duty returns as
            // automatic with today's points.
            $oldDuties = $version->hallDuties()->with('team')->tap($range)->get()
                ->keyBy(fn (HallDuty $duty): string => $this->hallDutyKey($duty->date->toDateString(), $duty->venue_id, $duty->start_time));
            $version->hallDuties()->tap($range)->delete();
            $teams = Team::query()->select(Team::PLANNER_COLUMNS)->get()->keyBy(fn (Team $team) => TeamCode::key($team->code));
            // The draft comes back from the browser: only this club's venues (or none)
            // and well-formed dates and times are saved.
            $venueIds = Venue::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($draft['hall_duties'] ?? [] as $duty) {
                $team = $teams->get(TeamCode::key($duty['team_code'] ?? null));
                if (!$team
                    || (filled($duty['venue_id'] ?? null) && !in_array((int) $duty['venue_id'], $venueIds, true))
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($duty['date'] ?? ''))
                    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) ($duty['start'] ?? ''))
                    || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) ($duty['end'] ?? ''))) {
                    continue;
                }
                $old = $oldDuties->get($this->hallDutyKey($duty['date'], (int) $duty['venue_id'], (string) $duty['start']));
                HallDuty::create([
                    'version_id' => $version->id,
                    'date' => $duty['date'], 'start_time' => $duty['start'], 'end_time' => $duty['end'],
                    'venue_id' => $duty['venue_id'], 'team_id' => $team->id,
                    // The duty keeps its points; only a new duty gets the default.
                    'points' => $old?->points,
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
     * Checks the assignments in a draft. A hard rule is an error and blocks
     * applying; a soft rule is shown as "Niet voorgesteld: ..." but is a
     * deliberate choice that is allowed.
     *
     * @param  bool  $singleVenue  Distinguishes "all venues" from a deliberately chosen block without venue.
     * @return array{matches: array<int, array<string, list<string>>>, hall_duties: array<int, list<string>>, count: int}
     */
    public function validate(ScheduleVersion $version, array $draft, ?string $date = null, ?int $venueId = null, bool $singleVenue = false): array
    {
        $context = PlanningContext::forVersion($version);
        $context->applyDraft($draft);
        $matches = $context->homeMatches()->keyBy('id');
        $result = ['matches' => [], 'hall_duties' => [], 'count' => 0];
        $judge = function (string $value, array $duty) use ($context): array {
            $team = $context->teamWithCode($value);

            return $team ? $this->rules->violations($team, $duty, $context) : [__('Het ingevulde team is niet gevonden.')];
        };
        $count = fn (array $reasons): int => array_filter($reasons, fn (string $reason): bool => !RuleEngine::isWarning($reason)) !== [] ? 1 : 0;

        foreach ($draft['matches'] ?? [] as $row) {
            $match = $matches->get($row['id'] ?? null);
            if (!$match || ($date !== null && $match->date->toDateString() !== $date) || ($singleVenue && $match->venue_id !== $venueId)) {
                continue;
            }
            foreach ([PlanningContext::DUTY_REFEREE, PlanningContext::DUTY_SCORER] as $field) {
                $value = trim((string) ($row[$field] ?? ''));
                if ($value === '' || ($field === PlanningContext::DUTY_REFEREE && strcasecmp($value, self::NEVOBO) === 0)) {
                    continue;
                }
                $reasons = $judge($value, $context->dutyForMatch($match, $field));
                if ($reasons) {
                    $result['matches'][$match->id][$field] = $reasons;
                    $result['count'] += $count($reasons);
                }
            }
        }

        foreach ($draft['hall_duties'] ?? [] as $index => $duty) {
            if (($date !== null && ($duty['date'] ?? null) !== $date) || ($singleVenue && ($duty['venue_id'] ?? null) !== $venueId)) {
                continue;
            }
            // A duty without a team is not filled in yet, not filled in
            // wrongly; that should not block applying the rest.
            $value = trim((string) ($duty['team_code'] ?? ''));
            if ($value === '') {
                continue;
            }

            $reasons = $judge($value, $context->dutyForHallDuty(
                (string) $duty['date'], isset($duty['venue_id']) ? (int) $duty['venue_id'] : null,
                (string) $duty['start'], (string) $duty['end'],
            ));
            if ($reasons) {
                $result['hall_duties'][$index] = $reasons;
                $result['count'] += $count($reasons);
            }
        }

        return $result;
    }

    /**
     * The team choice for one duty in the draft: all teams in the order of
     * step 3, with their standing and reasons.
     *
     * @param  'match'|'hall_duty'  $kind
     * @param  'referee'|'scorer'  $field
     * @return list<array<string, mixed>>
     */
    public function teamChoice(ScheduleVersion $version, array $draft, string $kind, int $index, string $field = PlanningContext::DUTY_SCORER): array
    {
        $context = PlanningContext::forVersion($version);
        $context->applyDraft($draft);
        $row = $draft[$kind === 'hall_duty' ? 'hall_duties' : 'matches'][$index] ?? null;
        if ($row === null) {
            return [];
        }

        if ($kind === 'hall_duty') {
            $date = (string) $row['date'];
            $venueId = isset($row['venue_id']) ? (int) $row['venue_id'] : null;
            // An existing hall duty keeps its own points.
            $duty = $context->dutyForHallDuty(
                $date, $venueId, (string) $row['start'], (string) $row['end'],
                $context->register->get($context->hallDutyKey($date, $venueId, (string) $row['start']))['stored_points'] ?? null,
            );
        } else {
            $match = $context->homeMatches()->firstWhere('id', $row['id'] ?? null);
            if (!$match) {
                return [];
            }
            $duty = $context->dutyForMatch($match, $field === PlanningContext::DUTY_REFEREE ? PlanningContext::DUTY_REFEREE : PlanningContext::DUTY_SCORER);
        }

        return self::candidatesForScreen($this->teamChoice->candidates($duty, $context), (int) $duty['points']);
    }

    /**
     * @param  list<array{team: Team, code: string, status: string, balance: PointsBalance, reasons: list<string>}>  $candidates
     * @return list<array<string, mixed>>
     */
    public static function candidatesForScreen(array $candidates, int $points = 0): array
    {
        return array_map(function (array $candidate) use ($points): array {
            // The balance before and after: the standing without this duty,
            // and with the duty added if the team gets it.
            $after = $candidate['balance']->withPlanned($candidate['balance']->planned + $points);

            return [
                'code' => $candidate['code'],
                'name' => (string) $candidate['team']->name,
                'status' => $candidate['status'],
                'reasons' => $candidate['reasons'],
                ...array_intersect_key($candidate['balance']->toArray(), array_flip([
                    'minimum', 'total', 'remaining', 'above_minimum', 'percentage', 'has_minimum',
                ])),
                'after_total' => $after->total(),
                'after_remaining' => $after->remaining(),
                'after_percentage' => $after->percentage(),
            ];
        }, $candidates);
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

    /** Identifies a hall duty by date, venue and start time. */
    private function hallDutyKey(string $date, ?int $venueId, string $start): string
    {
        return $date.'|'.$venueId.'|'.substr($start, 0, 5);
    }
}
