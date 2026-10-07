<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Lifecycle of a schedule version. */
enum ScheduleVersionStatus: string implements HasLabel
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => __('Concept'),
            self::Published => __('Gepubliceerd'),
            self::Archived => __('Gearchiveerd'),
        };
    }
}
