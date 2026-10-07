<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** What a team feed contains. The match import only reads schedule feeds. */
enum TeamSourceType: string implements HasLabel
{
    case Schedule = 'schedule';

    case Standings = 'standings';

    case Results = 'results';

    public function getLabel(): string
    {
        return match ($this) {
            self::Schedule => __('Programma'),
            self::Standings => __('Stand'),
            self::Results => __('Resultaten'),
        };
    }
}
