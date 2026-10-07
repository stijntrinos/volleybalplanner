<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Whether a match is played at home or away. */
enum MatchType: string implements HasLabel
{
    case Home = 'home';

    case Away = 'away';

    public function getLabel(): string
    {
        return match ($this) {
            self::Home => __('Thuis'),
            self::Away => __('Uit'),
        };
    }
}
