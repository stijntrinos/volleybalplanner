<?php

namespace App\Planner\Policies;

class VenueAliasPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'VenueAlias';
    }
}
