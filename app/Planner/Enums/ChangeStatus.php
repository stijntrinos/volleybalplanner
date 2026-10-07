<?php

namespace App\Planner\Enums;

use Filament\Support\Contracts\HasLabel;

/** Change state of a home match in our own planning. */
enum ChangeStatus: string implements HasLabel
{
    case Requested = 'requested';

    /** Has to move, but where to is not known yet: the match stays put and shows up in the list of matches still to plan; dragging it turns it into a regular request. */
    case ToChange = 'to_change';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => __('Aangevraagd'),
            self::ToChange => __('Te wijzigen'),
        };
    }
}
