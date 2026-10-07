<?php

namespace App\Planner\Support;

/**
 * The day planner draws the time axis in absolute pixels. The conversion
 * (minute − 480) × 1.2 was in the blade five times, again in the CSS heights
 * and reversed in the drag code. One shifted number and the cards sit next to
 * the clock, so the measurements belong in one place.
 */
final class Timetable
{
    /** The grid band starts at 08:00. */
    public const START_MINUTE = 480;

    /** The band spans 15.5 hours; 1116 px at 1.2 px per minute. */
    public const MINUTES = 930;

    public const PIXELS_PER_MINUTE = 1.2;

    /** Below 42 px the text on a card no longer fits. */
    public const MIN_HEIGHT = 42;

    public static function minutes(?string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', substr((string) $time, 0, 5))), 2, 0);

        return $hour * 60 + $minute;
    }

    public static function top(?string $time): float
    {
        return round((self::minutes($time) - self::START_MINUTE) * self::PIXELS_PER_MINUTE, 2);
    }

    public static function height(int|float $minutes): float
    {
        return round(max(self::MIN_HEIGHT, $minutes * self::PIXELS_PER_MINUTE), 2);
    }

    public static function heightBetween(?string $startTime, ?string $endTime): float
    {
        return self::height(self::minutes($endTime) - self::minutes($startTime));
    }

    public static function heightFrom(?string $startTime, ?string $endTime, int $fallbackMinutes = 60): float
    {
        return $endTime
            ? self::heightBetween($startTime, $endTime)
            : self::height($fallbackMinutes);
    }

    /**
     * Spreads blocks that overlap in time over columns side by side. Two
     * matches on the same court and time lay exactly on top of each other, so
     * the top one hid the bottom one and nobody saw the double booking.
     *
     * A group that touches shares the same width, so the cards of an overlap
     * line up neatly. Blocks that merely follow each other (11:45 after
     * 10:00–11:45) do not overlap and keep the full width.
     *
     * @param  list<array{id: int|string, start: int, end: int}>  $blocks  start and end in minutes
     * @return array<int|string, array{column: int, columns: int}>
     */
    public static function sideBySide(array $blocks): array
    {
        // Equal times keep the input order: the cards then sit side by side in
        // the order the planner shows them. usort is stable since PHP 8.
        usort($blocks, fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        $layout = [];
        $group = [];
        $lanes = [];
        $groupEnd = null;

        foreach ($blocks as $block) {
            if ($groupEnd !== null && $block['start'] >= $groupEnd) {
                $layout += self::widthForGroup($group, count($lanes));
                $group = [];
                $lanes = [];
                $groupEnd = null;
            }

            $lane = 0;
            while (isset($lanes[$lane]) && $lanes[$lane] > $block['start']) {
                $lane++;
            }
            $lanes[$lane] = $block['end'];
            $group[$block['id']] = $lane;
            $groupEnd = max($groupEnd ?? $block['end'], $block['end']);
        }

        return $layout + self::widthForGroup($group, count($lanes));
    }

    /**
     * @param  array<int|string, int>  $group
     * @return array<int|string, array{column: int, columns: int}>
     */
    private static function widthForGroup(array $group, int $columns): array
    {
        return array_map(
            fn (int $lane): array => ['column' => $lane, 'columns' => max(1, $columns)],
            $group,
        );
    }

    /** The measurements the drag code needs, as data attributes on the planner. */
    public static function attributes(): string
    {
        return sprintf(
            'data-timetable-start="%d" data-timetable-minutes="%d" data-timetable-scale="%s" data-timetable-step="15"',
            self::START_MINUTE,
            self::MINUTES,
            self::PIXELS_PER_MINUTE,
        );
    }
}
