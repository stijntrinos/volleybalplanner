<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Availability of a venue or court. */
enum AvailabilityStatus: string implements HasLabel
{
    case Available = 'available';

    case Partial = 'partial';

    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => __('Beschikbaar'),
            self::Partial => __('Gedeeltelijk'),
            self::Closed => __('Gesloten'),
        };
    }
}
