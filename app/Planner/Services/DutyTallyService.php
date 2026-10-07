<?php

namespace App\Planner\Services;

use App\Planner\Features\PlannerFeature;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\DutyPlanner;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;

/**
 * Tallies the duties per team of a schedule version (current club).
 *
 * With the volunteer_points module the duty columns hold points (as set in
 * the planner settings) and the rows carry used, earned and percentage.
 * Without it every duty counts as one and those columns are left out. The
 * volleystars column only exists with the volleystars module.
 */
class DutyTallyService
{
    /**
     * The duty tally per team. With a period only the duties within those
     * dates count; used and earned remain the team's standings.
     *
     * @param  ?string  $from  Y-m-d, inclusive
     * @param  ?string  $to  Y-m-d, inclusive
     * @return list<array{team: string, used?: int, earned?: int, referee: int, scorer: int, hall_duty: int, volleystars?: int, total: int, percentage?: float}>
     */
    public function perTeam(ScheduleVersion $version, ?string $from = null, ?string $to = null): array
    {
        $club = app(CurrentClub::class)->require();
        $withPoints = $club->hasFeature(PlannerFeature::VolunteerPoints);
        $withVolleystars = $club->hasFeature(PlannerFeature::Volleystars);
        $period = fn ($query) => $query
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to));

        $result = Team::query()->select(Team::PLANNER_COLUMNS)->where('include_volunteers', true)->get()
            ->mapWithKeys(fn (Team $team): array => [TeamCode::key($team->code) => array_filter([
                'team' => trim((string) $team->code),
                'used' => $withPoints ? (int) $team->volunteer_points_used : null,
                'earned' => $withPoints ? (int) $team->volunteer_points_earned : null,
                'referee' => 0,
                'scorer' => 0,
                'hall_duty' => 0,
                'volleystars' => $withVolleystars ? 0 : null,
                'total' => 0,
                'percentage' => $withPoints ? 0.0 : null,
            ], fn (mixed $value): bool => $value !== null)])->all();
        $points = $this->points($withPoints);

        // Every duty as [team code, duty column], tallied in one pass below.
        $duties = [];
        foreach ($version->matches()->tap($period)->get(['referee', 'scorer']) as $match) {
            foreach (['referee', 'scorer'] as $duty) {
                $code = trim((string) $match->{$duty});
                if ($code === '' || strcasecmp($code, DutyPlanner::NEVOBO) === 0) {
                    continue;
                }
                $duties[] = [$code, $duty];
            }
        }
        foreach ($version->hallDuties()->tap($period)->with('team')->get() as $hallDuty) {
            $duties[] = [$hallDuty->team->code, 'hall_duty'];
        }
        if ($withVolleystars) {
            foreach ($version->volleystarsBlocks()->tap($period)->with('tasks.team')->get()->flatMap->tasks as $task) {
                if ($task->team) {
                    $duties[] = [$task->team->code, 'volleystars'];
                }
            }
        }

        foreach ($duties as [$code, $duty]) {
            $key = TeamCode::key($code);
            if ($key === '' || !array_key_exists($key, $result)) {
                continue;
            }

            $result[$key][$duty] += $points[$duty];
            $result[$key]['total'] += $points[$duty];
        }

        if ($withPoints) {
            foreach ($result as &$row) {
                $earned = $row['earned'] + $row['total'];
                $row['percentage'] = $row['used'] > 0
                    ? round((($row['used'] - $earned) / $row['used']) * 100, 1)
                    : 0.0;
            }
            unset($row);
        }

        $rows = array_values($result);
        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strnatcasecmp($a['team'], $b['team']));

        return $rows;
    }

    /** @return array{referee: int, scorer: int, hall_duty: int, volleystars: int} */
    private function points(bool $withPoints): array
    {
        if (!$withPoints) {
            return ['referee' => 1, 'scorer' => 1, 'hall_duty' => 1, 'volleystars' => 1];
        }

        $settings = PlannerSetting::current();

        return [
            'referee' => (int) $settings->points_referee_duty,
            'scorer' => (int) $settings->points_scorer_duty,
            'hall_duty' => (int) $settings->points_hall_duty,
            'volleystars' => (int) $settings->points_volleystars,
        ];
    }
}
