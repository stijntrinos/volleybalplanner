<?php

namespace App\Planner\Policies;

class ScheduleVersionPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'ScheduleVersion';
    }
}
