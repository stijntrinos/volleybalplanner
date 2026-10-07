<?php

namespace App\Planner\Policies;

class TeamPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Team';
    }
}
