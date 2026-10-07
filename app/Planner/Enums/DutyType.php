<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** The duty (FTZ) a change request is about. */
enum DutyType: string implements HasLabel
{
    case Referee = 'referee';

    case Scorer = 'scorer';

    case HallDuty = 'hall_duty';

    public function getLabel(): string
    {
        return match ($this) {
            self::Referee => __('Fluiten'),
            self::Scorer => __('Tellen'),
            self::HallDuty => __('Zaaldienst'),
        };
    }
}
