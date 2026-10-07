<?php

namespace App\Planner\Policies;

class VolleystarsBlockPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'VolleystarsBlock';
    }
}
