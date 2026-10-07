<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Progress of a change to an away match, agreed with the opponent. */
enum AwayChangeStatus: string implements HasLabel
{
    case None = 'none';

    case InProgress = 'in_progress';

    case InDiscussion = 'in_discussion';

    case Final = 'final';

    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => __('Geen'),
            self::InProgress => __('Loopt'),
            self::InDiscussion => __('Overleg'),
            self::Final => __('Definitief'),
            self::Cancelled => __('Afgelast'),
        };
    }
}
