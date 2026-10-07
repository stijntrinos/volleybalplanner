<?php

namespace App\Planner\Policies;

class CourtPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Court';
    }
}
