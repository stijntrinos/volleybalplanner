<?php

namespace App\Planner\Policies;

class ActivityPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Activity';
    }
}
