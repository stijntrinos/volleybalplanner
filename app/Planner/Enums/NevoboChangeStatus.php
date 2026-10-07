<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** How a change published by Nevobo relates to our planning. */
enum NevoboChangeStatus: string implements HasLabel
{
    /** Nevobo matches what we have; nothing to report. */
    case None = 'none';

    /** Nevobo moved a match we had not planned ourselves, so the new values were applied. */
    case Applied = 'applied';

    /** Nevobo moved a match we had already planned; their values are parked for review. */
    case Conflict = 'conflict';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('Geen'),
            self::Applied => __('Doorgevoerd'),
            self::Conflict => __('Conflict'),
        };
    }

    /**
     * The statuses that still need attention in the Nevobo changes overview.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Applied, self::Conflict];
    }
}
