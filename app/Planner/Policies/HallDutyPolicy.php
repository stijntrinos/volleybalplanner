<?php

namespace App\Planner\Policies;

class HallDutyPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'HallDuty';
    }
}
