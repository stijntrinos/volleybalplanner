<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\VolleystarsTask;

/**
 * The points of a duty. A new duty gets the default points that are in the
 * planner settings at that moment; it keeps them after that, also when the
 * volunteer committee changes the default later.
 */
final class DutyPoints
{
    /** @return array{referee: int, scorer: int, hall_duty: int, volleystars: int} */
    public static function defaults(?PlannerSetting $settings): array
    {
        return [
            'referee' => (int) $settings?->points_referee_duty,
            'scorer' => (int) $settings?->points_scorer_duty,
            'hall_duty' => (int) $settings?->points_hall_duty,
            'volleystars' => (int) $settings?->points_volleystars,
        ];
    }

    /** The defaults of the current club, without creating its settings row. */
    public static function currentDefaults(): array
    {
        return self::defaults(PlannerSetting::query()->first());
    }

    public static function fillMatch(FixtureMatch $match): void
    {
        if ($match->match_type !== MatchType::Home
            || ($match->referee_points !== null && $match->scorer_points !== null)) {
            return;
        }

        $defaults = self::currentDefaults();
        $match->referee_points ??= $defaults['referee'];
        $match->scorer_points ??= $defaults['scorer'];
    }

    public static function fillHallDuty(HallDuty $duty): void
    {
        $duty->points ??= self::currentDefaults()['hall_duty'];
    }

    public static function fillVolleystarsTask(VolleystarsTask $task): void
    {
        $task->points ??= self::currentDefaults()['volleystars'];
    }
}
