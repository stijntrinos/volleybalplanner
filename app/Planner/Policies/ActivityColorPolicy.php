<?php

namespace App\Planner\Policies;

class ActivityColorPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'ActivityColor';
    }
}
