<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Fixed change-request reasons. All other reasons are free text configured in the planner settings. */
enum ChangeReason: string implements HasLabel
{
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Other => __('Overig'),
        };
    }
}
