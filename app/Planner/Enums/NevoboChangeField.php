<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** The match field a processed Nevobo change refers to. */
enum NevoboChangeField: string implements HasLabel
{
    case Date = 'date';

    case Time = 'time';

    case Venue = 'venue';

    case Court = 'court';

    public function getLabel(): string
    {
        return match ($this) {
            self::Date => __('Datum'),
            self::Time => __('Tijd'),
            self::Venue => __('Sporthal'),
            self::Court => __('Veld'),
        };
    }
}
