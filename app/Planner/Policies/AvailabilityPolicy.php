<?php

namespace App\Planner\Policies;

class AvailabilityPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Availability';
    }
}
