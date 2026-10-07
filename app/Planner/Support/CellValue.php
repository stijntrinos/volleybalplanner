<?php

namespace App\Planner\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A cell from a Nevobo export is sometimes text ("05-09-2026", "19:30") and
 * sometimes the number Excel stores a date or time as (46268, 0.8125). All
 * importers read them the same way, so that conversion lives here once.
 */
final class CellValue
{
    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim((string) $value))->toDateString();
            } catch (Throwable) {
            }
        }

        // Unreadable stays unreadable: a single cell like "n.t.b." must not
        // abort the import, so no exception but null.
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '00:00' || $value === '00:00:00') {
            return null;
        }
        if (is_numeric($value)) {
            $minutes = (int) round(((float) $value - floor((float) $value)) * 1440);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        return substr(trim((string) $value), 0, 5);
    }
}
