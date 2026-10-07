<?php

namespace App\Planner\Support;

use Filament\Support\Contracts\HasLabel;

enum ClubRole: string implements HasLabel
{
    case ClubAdmin = 'club_admin';
    case PlanningCommittee = 'planning_committee';

    public function getLabel(): string
    {
        return __('planner.roles.'.$this->value);
    }
}
