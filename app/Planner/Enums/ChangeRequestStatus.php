<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Decision state of a change request. */
enum ChangeRequestStatus: string implements HasColor, HasLabel
{
    case Open = 'open';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    /** Not decided yet but being handled; no longer counts as new and stays marked in the planner. */
    case Parked = 'parked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Accepted => __('Geaccepteerd'),
            self::Rejected => __('Afgewezen'),
            self::Parked => __('Geparkeerd'),
        };
    }

    /**
     * The statuses that still await a decision.
     *
     * @return list<self>
     */
    public static function undecided(): array
    {
        return [self::Open, self::Parked];
    }

    /** Badge colour; table and modal both show it, so it lives in one place. */
    public function getColor(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Parked => 'info',
            self::Open => 'warning',
        };
    }
}
