<?php

namespace App\Planner\Policies;

class TeamSourcePolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'TeamSource';
    }
}
