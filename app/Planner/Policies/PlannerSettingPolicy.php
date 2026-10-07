<?php

namespace App\Planner\Policies;

class PlannerSettingPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'PlannerSetting';
    }
}
