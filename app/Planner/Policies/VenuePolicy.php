<?php

namespace App\Planner\Policies;

class VenuePolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Venue';
    }
}
