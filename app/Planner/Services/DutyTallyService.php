<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\DutyPlanner;
use App\Planner\Services\Duties\DutyPoints;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;

/**
 * Tallies the duties per team of a schedule version (current club) against
 * the team's minimum.
 *
 * With the volunteer_points module every duty counts with its own points and
 * the rows carry the minimum and what is still to fill. Without it every duty
 * counts as one and no team has a minimum. The volleystars column only counts
 * with the volleystars module.
 */
class DutyTallyService
{
    /**
     * The standing per team against the minimum. With a period only the
     * duties within those dates count; need, minimum and earned remain the
     * values of the whole season.
     *
     * @param  ?string  $from  Y-m-d, inclusive
     * @param  ?string  $to  Y-m-d, inclusive
     * @return list<array{team: string, need: int, minimum_percentage: ?int, minimum: int, earned: int, planned: int, total: int, remaining: int, above_minimum: int, percentage: ?float, has_minimum: bool, referee: int, scorer: int, hall_duty: int, volleystars: int}>
     */
    public function perTeam(ScheduleVersion $version, ?string $from = null, ?string $to = null): array
    {
        $withPoints = $this->withPoints();
        $planned = $this->plannedPerTeam($version, $from, $to);

        $rows = Team::query()->select(Team::PLANNER_COLUMNS)->where('include_volunteers', true)->get()
            ->map(function (Team $team) use ($planned, $withPoints): array {
                $duties = $planned[TeamCode::key($team->code)] ?? self::empty();

                return ['team' => trim((string) $team->code)]
                    + PointsBalance::forTeam($team, $duties['total'], $withPoints)->toArray()
                    + array_diff_key($duties, ['total' => true]);
            })
            ->values()
            ->all();

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strnatcasecmp($a['team'], $b['team']));

        return $rows;
    }

    /** The standing of one team in the published schedule, for the team form. */
    public function balanceOfTeam(Team $team): PointsBalance
    {
        $version = self::displayableVersion();
        $planned = $version ? ($this->plannedPerTeam($version)[TeamCode::key($team->code)]['total'] ?? 0) : 0;

        return PointsBalance::forTeam($team, $planned, $this->withPoints());
    }

    /**
     * The points of the duties assigned to a team in the duty planning, per
     * kind. Open duties do not count.
     *
     * @return array<string, array{referee: int, scorer: int, hall_duty: int, volleystars: int, total: int}>
     */
    public function plannedPerTeam(ScheduleVersion $version, ?string $from = null, ?string $to = null): array
    {
        $club = app(CurrentClub::class)->require();
        $withPoints = $club->hasFeature(PlannerFeature::VolunteerPoints);
        $period = fn ($query) => $query
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to));
        $defaults = DutyPoints::defaults(PlannerSetting::query()->first());
        $points = fn (?int $stored, string $duty): int => $withPoints ? ($stored ?? $defaults[$duty]) : 1;
        $result = [];

        $tally = function (?string $code, string $duty, int $points) use (&$result): void {
            $key = TeamCode::key($code);
            if ($key === '' || $key === TeamCode::key(DutyPlanner::NEVOBO)) {
                return;
            }

            $result[$key] ??= self::empty();
            $result[$key][$duty] += $points;
            $result[$key]['total'] += $points;
        };

        $version->matches()->tap($period)
            ->where('match_type', MatchType::Home)
            ->get(['referee', 'scorer', 'referee_points', 'scorer_points'])
            ->each(function (FixtureMatch $match) use ($tally, $points): void {
                $tally($match->referee, 'referee', $points($match->referee_points, 'referee'));
                $tally($match->scorer, 'scorer', $points($match->scorer_points, 'scorer'));
            });
        $version->hallDuties()->tap($period)->with('team')->get()->each(
            fn ($duty) => $tally($duty->team->code, 'hall_duty', $points($duty->points, 'hall_duty')),
        );
        if ($club->hasFeature(PlannerFeature::Volleystars)) {
            $version->volleystarsBlocks()->tap($period)->with('tasks.team')->get()->flatMap->tasks->each(
                fn ($task) => $tally($task->team?->code, 'volleystars', $points($task->points, 'volleystars')),
            );
        }

        return $result;
    }

    /** The schedule the overviews work with: the published one, otherwise the newest draft. */
    public static function displayableVersion(): ?ScheduleVersion
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->latest('id')->first()
            ?? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->latest('id')->first();
    }

    private function withPoints(): bool
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::VolunteerPoints);
    }

    /** @return array{referee: int, scorer: int, hall_duty: int, volleystars: int, total: int} */
    private static function empty(): array
    {
        return ['referee' => 0, 'scorer' => 0, 'hall_duty' => 0, 'volleystars' => 0, 'total' => 0];
    }
}
