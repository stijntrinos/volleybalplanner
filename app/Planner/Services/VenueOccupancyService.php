<?php

namespace App\Planner\Services;

use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Models\Availability;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Support\Collection;

/**
 * Puts side by side, per venue and per match day, what has been rented and
 * what is planned, so it is visible at a glance where it pinches: a match
 * that starts before the venue opens, a day for which nobody recorded the
 * rental, or a venue that is closed while there is play anyway.
 */
class VenueOccupancyService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_CONFLICT = 'conflict';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUSES = [
        self::STATUS_AVAILABLE, self::STATUS_PARTIAL, self::STATUS_CONFLICT, self::STATUS_CLOSED, self::STATUS_UNKNOWN,
    ];

    public function __construct(private readonly ConflictEngine $conflicts) {}

    /** @return array<string, string> status => translated label */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_AVAILABLE => __('Beschikbaar'),
            self::STATUS_PARTIAL => __('Deels beschikbaar'),
            self::STATUS_CONFLICT => __('Conflict'),
            self::STATUS_CLOSED => __('Niet beschikbaar'),
            self::STATUS_UNKNOWN => __('Geen beschikbaarheid opgegeven'),
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? $status;
    }

    /**
     * @param  list<string>  $statuses  empty list means: all statuses
     * @return array{
     *     venues: list<array{venue_id: int, venue: string, rows: list<array<string, mixed>>}>,
     *     total: int, conflicts: int, from: string, to: string
     * }
     */
    public function overview(
        ScheduleVersion $version,
        string $fromDate,
        string $toDate,
        ?int $venueId = null,
        array $statuses = [],
        bool $matchDaysOnly = false,
    ): array {
        /** @var ModelCollection<int, FixtureMatch> $matches */
        $matches = $version->matches()
            ->where('match_type', MatchType::Home)
            ->whereNotNull('date')
            // whereDate and not whereBetween: with a date cast sqlite stores a
            // full timestamp, so the upper bound drops the last day.
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->with(['venue', 'court'])
            ->orderBy('date')->orderBy('time')
            ->get();

        /** @var ModelCollection<int, Availability> $rules */
        $rules = Availability::query()->get();
        $venues = Venue::query()
            ->when($venueId, fn ($query) => $query->whereKey($venueId))
            ->inPlanningOrder()->get();

        $result = [];
        $total = 0;
        $conflicts = 0;

        foreach ($venues as $venue) {
            $perVenue = $matches->where('venue_id', $venue->id);
            // Which rules apply on which day, computed once per venue. Running
            // through all rules of the club again per row took over one and a
            // half seconds for a whole season with weekday rules.
            $applicable = $this->applicablePerDay($fromDate, $toDate, $rules->where('venue_id', $venue->id));
            $perDay = $perVenue->groupBy(fn (FixtureMatch $m): string => $m->date->toDateString());
            $dates = $this->dates($perDay, $applicable, $matchDaysOnly);
            $venueRows = [];

            foreach ($dates as $date) {
                $row = $this->rowForDay($date, $perDay->get($date, new ModelCollection), $applicable[$date] ?? []);

                if ($statuses !== [] && !in_array($row['status'], $statuses, true)) {
                    continue;
                }

                $venueRows[] = $row;
                $total++;
                if ($row['status'] === self::STATUS_CONFLICT) {
                    $conflicts++;
                }
            }

            if ($venueRows !== []) {
                $result[] = ['venue_id' => $venue->id, 'venue' => $venue->name, 'rows' => $venueRows];
            }
        }

        return [
            'venues' => $result,
            'total' => $total,
            'conflicts' => $conflicts,
            'from' => $fromDate,
            'to' => $toDate,
        ];
    }

    /**
     * Per day in the period the rules of this venue that apply on that day,
     * including rules for a single court.
     *
     * @param  Collection<int, Availability>  $venueRules
     * @return array<string, list<Availability>>
     */
    private function applicablePerDay(string $from, string $to, Collection $venueRules): array
    {
        if ($venueRules->isEmpty()) {
            return [];
        }

        $applicable = [];
        $day = CarbonImmutable::parse($from);
        $last = CarbonImmutable::parse($to);

        while ($day <= $last) {
            $date = $day->toDateString();
            $onDay = $venueRules->filter(fn (Availability $r): bool => $this->conflicts->ruleAppliesOn($r, $date))->values()->all();
            if ($onDay !== []) {
                $applicable[$date] = $onDay;
            }
            $day = $day->addDay();
        }

        return $applicable;
    }

    /**
     * The days reported on: every day with play, and also every day for
     * which something about availability was recorded -- otherwise a closed
     * venue never shows up.
     *
     * @param  Collection<string, ModelCollection<int, FixtureMatch>>  $perDay
     * @param  array<string, list<Availability>>  $applicable
     * @return list<string>
     */
    private function dates(Collection $perDay, array $applicable, bool $matchDaysOnly): array
    {
        $matchDays = $perDay->keys()->toBase();

        if ($matchDaysOnly) {
            return $matchDays->sort()->values()->all();
        }

        return $matchDays->merge(array_keys($applicable))->unique()->sort()->values()->all();
    }

    /**
     * @param  ModelCollection<int, FixtureMatch>  $onDay  this venue's matches on this day
     * @param  list<Availability>  $applicable  this venue's rules that apply on this day
     * @return array<string, mixed>
     */
    private function rowForDay(string $date, ModelCollection $onDay, array $applicable): array
    {
        $onDay = $onDay->sortBy(fn (FixtureMatch $m): string => (string) $m->time)->values();

        $venueRule = collect($applicable)
            ->filter(fn (Availability $r): bool => $r->court_id === null)
            ->sortByDesc(fn (Availability $r): int => $this->conflicts->scopeWeight($r))
            ->first();

        $first = $onDay->first();
        $last = $onDay->last();
        $endOfLast = $last && $last->time
            ? CarbonImmutable::createFromFormat('H:i', substr((string) $last->time, 0, 5))
                ->addMinutes((int) $last->duration_minutes)->format('H:i')
            : null;

        $details = [];
        foreach ($onDay as $match) {
            $start = $match->time ? substr((string) $match->time, 0, 5) : null;
            $details[] = [
                'code' => $match->match_code,
                'home' => $match->home_team_name,
                'away' => $match->away_team_name,
                'court' => $match->court?->name,
                'start' => $start,
                'end' => $start
                    ? CarbonImmutable::createFromFormat('H:i', $start)->addMinutes((int) $match->duration_minutes)->format('H:i')
                    : null,
                'duration' => $match->duration_minutes.' min',
            ];
        }

        return [
            'date' => $date,
            'day' => mb_strtolower(Weekday::abbreviation($date)),
            'available_from' => $venueRule?->hasTimeWindow() ? substr((string) $venueRule->start_time, 0, 5) : null,
            'available_until' => $venueRule?->hasTimeWindow() ? substr((string) $venueRule->end_time, 0, 5) : null,
            'matches' => $onDay->count(),
            'first' => $first?->time ? substr((string) $first->time, 0, 5) : null,
            'last' => $last?->time ? substr((string) $last->time, 0, 5) : null,
            'end_of_last' => $endOfLast,
            'status' => $this->status($venueRule, $onDay, $first, $endOfLast),
            'note' => $venueRule?->note,
            'details' => $details,
        ];
    }

    /** @param Collection<int, FixtureMatch> $onDay */
    private function status(?Availability $rule, Collection $onDay, ?FixtureMatch $first, ?string $endOfLast): string
    {
        if (!$rule instanceof Availability) {
            return self::STATUS_UNKNOWN;
        }

        if ($rule->status === AvailabilityStatus::Closed) {
            // Closed and still planned is the worst case; it must stand out.
            return $onDay->isEmpty() ? self::STATUS_CLOSED : self::STATUS_CONFLICT;
        }

        if ($rule->status === AvailabilityStatus::Partial) {
            return self::STATUS_PARTIAL;
        }

        // Available with a window: does everything fall within it?
        if ($rule->hasTimeWindow() && $first?->time && $endOfLast) {
            $from = substr((string) $rule->start_time, 0, 5);
            $until = substr((string) $rule->end_time, 0, 5);
            if (substr((string) $first->time, 0, 5) < $from || $endOfLast > $until) {
                return self::STATUS_CONFLICT;
            }
        }

        return self::STATUS_AVAILABLE;
    }
}
