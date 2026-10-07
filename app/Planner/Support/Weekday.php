<?php

namespace App\Planner\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The planner knows three notations for a weekday: "maandag" in
 * planner_trainings.weekday, "Ma" in planner_teams.default_weekday, and a
 * number (0 is Sunday) in the availability rules. The stored values follow the
 * Dutch spreadsheets they are imported from; converting between them happens
 * here, in one place.
 *
 * This deliberately does not use Carbon's localised dayName: without the Dutch
 * locale on the server that returns "saturday" and no training is ever found.
 */
final class Weekday
{
    /** As planner_trainings.weekday stores them. */
    public const NAMES = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

    /** As planner_teams.default_weekday stores them. */
    public const ABBREVIATIONS = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

    public static function name(CarbonInterface|string $date): string
    {
        return self::NAMES[self::index($date)];
    }

    public static function abbreviation(CarbonInterface|string $date): string
    {
        return self::ABBREVIATIONS[self::index($date)];
    }

    /**
     * 0 is Sunday, 1 to 6 are Monday to Saturday.
     *
     * @return 0|1|2|3|4|5|6
     */
    public static function number(CarbonInterface|string $date): int
    {
        return (self::index($date) + 1) % 7;
    }

    public static function nameFromAbbreviation(?string $abbreviation): ?string
    {
        $position = array_search(ucfirst(mb_strtolower((string) $abbreviation)), self::ABBREVIATIONS, true);

        return $position === false ? null : self::NAMES[$position];
    }

    public static function abbreviationFromName(?string $name): ?string
    {
        $position = array_search(mb_strtolower((string) $name), self::NAMES, true);

        return $position === false ? null : self::ABBREVIATIONS[$position];
    }

    /** 0 is Monday, 6 is Sunday. */
    private static function index(CarbonInterface|string $date): int
    {
        $day = $date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date);

        return $day->isoWeekday() - 1;
    }
}
