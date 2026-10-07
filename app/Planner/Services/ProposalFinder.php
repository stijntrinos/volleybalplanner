<?php

namespace App\Planner\Services;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use Carbon\CarbonImmutable;

/**
 * Searches free, conflict-free slots for a match on a given court: the same
 * weekday in the weeks around the current date, within the competition.
 */
class ProposalFinder
{
    public const MAX_CHECKS = 60;

    public const PHASE_TEAM_PREFERENCE = 'team_preference';

    public const PHASE_ORIGINAL_TIME = 'original_time';

    public const PHASE_ALTERNATIVE_SLOT = 'alternative_slot';

    public function __construct(private readonly AvailabilityChecker $checker) {}

    /**
     * @param  list<string>  $skip  "Y-m-d H:i" slots already reviewed
     * @return list<array<string, mixed>> placements (date, time, duration_minutes, venue_id, court_id) plus phase and check
     */
    public function find(FixtureMatch $match, int $venueId, int $courtId, array $skip = [], int $limit = 10): array
    {
        $settings = PlannerSetting::current();
        $base = CarbonImmutable::parse($match->date ?? $match->original_date ?? now());
        $from = $settings->competition_start ? CarbonImmutable::parse($settings->competition_start) : $base->subWeeks(12);
        $until = $settings->competition_end ? CarbonImmutable::parse($settings->competition_end) : $base->addWeeks(12);
        $originalTime = substr((string) ($match->time ?: $match->original_time ?: '10:00'), 0, 5);
        $teamPreference = $match->homeTeam?->default_time
            ? substr((string) $match->homeTeam->default_time, 0, 5)
            : null;
        $dates = [];
        for ($week = 0; $week <= 12; $week++) {
            foreach ($week === 0 ? [0] : [-$week, $week] as $offset) {
                $date = $base->addWeeks($offset);
                if ($date->betweenIncluded($from, $until) && $date->greaterThanOrEqualTo(today())) {
                    $dates[] = $date;
                }
            }
        }

        // The team's fixed time on the current match day is the most useful
        // proposal. That way e.g. the configured 17:00 for H2 shows up right
        // away when the match is now at 19:30.
        $candidates = [];
        $baseIsCandidate = collect($dates)->contains(fn (CarbonImmutable $date): bool => $date->isSameDay($base));
        if ($teamPreference !== null && $teamPreference !== $originalTime && $baseIsCandidate) {
            $candidates[] = [$base, $teamPreference, self::PHASE_TEAM_PREFERENCE];
        }

        // First every date at the current time, then round by round an
        // alternative time slot per date. Going date by date spent the limit
        // on the first two match days, so the assistant hardly ever proposed
        // another time.
        $candidates = [...$candidates, ...array_map(fn ($date): array => [$date, $originalTime, self::PHASE_ORIGINAL_TIME], $dates)];
        if ($teamPreference !== null && $teamPreference !== $originalTime) {
            foreach ($dates as $date) {
                if ($date->isSameDay($base)) {
                    continue;
                }
                $candidates[] = [$date, $teamPreference, self::PHASE_TEAM_PREFERENCE];
            }
        }
        for ($minutes = 600; $minutes <= 1020; $minutes += 30) {
            $slot = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
            if ($slot === $originalTime) {
                continue;
            }
            foreach ($dates as $date) {
                $candidates[] = [$date, $slot, self::PHASE_ALTERNATIVE_SLOT];
            }
        }

        // Slots already reviewed drop out before truncating: otherwise they
        // reduce the number of checks without giving an alternative back.
        $unique = [];
        $candidates = array_values(array_filter($candidates, function (array $candidate) use ($skip, &$unique): bool {
            $key = $candidate[0]->toDateString().' '.$candidate[1];
            if (isset($unique[$key]) || in_array($key, $skip, true)) {
                return false;
            }

            $unique[$key] = true;

            return true;
        }));

        // The external feeds belong to the match, not to the time slot: fetch
        // once instead of again for each of the dozens of checks.
        $externalMatches = $this->checker->externalMatches($match);

        $results = [];
        foreach (array_slice($candidates, 0, self::MAX_CHECKS) as [$date, $time, $phase]) {
            $placement = [
                'date' => $date->toDateString(), 'time' => $time,
                'duration_minutes' => $match->duration_minutes, 'venue_id' => $venueId, 'court_id' => $courtId,
            ];
            $check = $this->checker->check($match, $placement, $externalMatches, log: false);
            if ($check['result'] === AvailabilityChecker::RESULT_GREEN) {
                $results[] = $placement + ['phase' => $phase, 'check' => $check];
            }
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }
}
