<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** What a change request is about. */
enum ChangeRequestKind: string implements HasLabel
{
    case Match = 'match';

    case Duty = 'duty';

    public function getLabel(): string
    {
        return match ($this) {
            self::Match => __('Wedstrijd'),
            self::Duty => __('FTZ-dienst'),
        };
    }
}
