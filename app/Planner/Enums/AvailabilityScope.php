<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** What an availability rule applies to: one date, a weekday, or a period. */
enum AvailabilityScope: string implements HasLabel
{
    case Date = 'date';

    case Weekday = 'weekday';

    case Period = 'period';

    public function getLabel(): string
    {
        return match ($this) {
            self::Date => __('Datum'),
            self::Weekday => __('Weekdag'),
            self::Period => __('Periode'),
        };
    }
}
