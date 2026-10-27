<?php

namespace App\Planner\Services\Duties;

use App\Planner\Support\TeamCode;
use Carbon\CarbonImmutable;

/**
 * All assigned duties of a schedule, queryable per team. The rules about the
 * weekly and daily maximum, adjoining duties and the fair share look at it;
 * the generator updates the register with every choice, so the next duty
 * sees the new standing.
 *
 * @phpstan-type Duty array<string, mixed>
 */
final class DutyRegister
{
    /** @var array<string, Duty> */
    private array $duties = [];

    /** @var array<string, array<string, true>> team key => duty keys */
    private array $perTeam = [];

    /** @var array<string, int> */
    private array $points = [];

    /** @param Duty $duty */
    public function put(array $duty): void
    {
        $this->remove($duty['key']);
        $team = TeamCode::key($duty['team']);
        if ($team === '' || $team === TeamCode::key(DutyPlanner::NEVOBO)) {
            return;
        }

        $duty['team'] = $team;
        $this->duties[$duty['key']] = $duty;
        $this->perTeam[$team][$duty['key']] = true;
        $this->points[$team] = ($this->points[$team] ?? 0) + $duty['points'];
    }

    public function remove(string $key): void
    {
        $old = $this->duties[$key] ?? null;
        if ($old === null) {
            return;
        }

        unset($this->duties[$key], $this->perTeam[$old['team']][$key]);
        $this->points[$old['team']] -= $old['points'];
    }

    /** @return Duty|null */
    public function get(string $key): ?array
    {
        return $this->duties[$key] ?? null;
    }

    /** @return list<Duty> */
    public function ofTeam(string $teamCode, ?string $except = null): array
    {
        $result = [];
        foreach (array_keys($this->perTeam[TeamCode::key($teamCode)] ?? []) as $key) {
            if ($key !== $except) {
                $result[] = $this->duties[$key];
            }
        }

        return $result;
    }

    /** @return list<Duty> */
    public function ofTeamOnDay(string $teamCode, string $date, ?string $except = null): array
    {
        return array_values(array_filter(
            $this->ofTeam($teamCode, $except),
            static fn (array $duty): bool => $duty['date'] === $date,
        ));
    }

    /** The number of duties in the week (Monday to Sunday) of this date. */
    public function countInWeek(string $teamCode, string $date, ?string $except = null): int
    {
        $monday = CarbonImmutable::parse($date)->startOfWeek()->toDateString();
        $sunday = CarbonImmutable::parse($date)->endOfWeek()->toDateString();

        return count(array_filter(
            $this->ofTeam($teamCode, $except),
            static fn (array $duty): bool => $duty['date'] >= $monday && $duty['date'] <= $sunday,
        ));
    }

    /** The points of all planned duties of the team. */
    public function points(string $teamCode): int
    {
        return $this->points[TeamCode::key($teamCode)] ?? 0;
    }

    /** The points of the duties between two dates, both inclusive. */
    public function pointsBetween(string $teamCode, string $from, string $to, ?string $except = null): int
    {
        return array_sum(array_map(
            static fn (array $duty): int => $duty['date'] >= $from && $duty['date'] <= $to ? $duty['points'] : 0,
            $this->ofTeam($teamCode, $except),
        ));
    }

    /** The points of the duties before a date. */
    public function pointsBefore(string $teamCode, string $date): int
    {
        return array_sum(array_map(
            static fn (array $duty): int => $duty['date'] < $date ? $duty['points'] : 0,
            $this->ofTeam($teamCode),
        ));
    }

    public function count(string $teamCode, ?string $except = null): int
    {
        return count($this->ofTeam($teamCode, $except));
    }
}
