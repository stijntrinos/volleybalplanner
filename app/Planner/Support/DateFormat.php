<?php

namespace App\Planner\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single date and time notation of the planner: dates dd-mm-yyyy, times
 * HH:mm, optionally preceded by the weekday where that helps planning.
 */
final class DateFormat
{
    public const DATE = 'd-m-Y';

    public const TIME = 'H:i';

    public const DATE_TIME = 'd-m-Y H:i';

    public const WITH_WEEKDAY = 'l d-m-Y';

    public const SHORT_WITH_WEEKDAY = 'D d-m-Y';

    public static function date(mixed $value): ?string
    {
        return self::parse($value)?->format(self::DATE);
    }

    public static function time(mixed $value): ?string
    {
        // Times arrive as "19:30" and "19:30:00"; parsing would turn a bare
        // time into today's date, so cutting is enough.
        $text = trim((string) $value);

        return $text === '' ? null : substr($text, 0, 5);
    }

    public static function dateTime(mixed $value): ?string
    {
        return self::parse($value)?->format(self::DATE_TIME);
    }

    public static function withWeekday(mixed $value, bool $short = false): ?string
    {
        $date = self::parse($value);

        return $date
            ? Str::ucfirst($date->locale('nl')->translatedFormat($short ? self::SHORT_WITH_WEEKDAY : self::WITH_WEEKDAY))
            : null;
    }

    /** Heading above a group of days, e.g. "September 2026". */
    public static function month(mixed $value): ?string
    {
        $date = self::parse($value);

        return $date ? Str::ucfirst($date->locale('nl')->translatedFormat('F Y')) : null;
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
