<?php

namespace App\Planner\Policies;

class VolleystarsTaskPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'VolleystarsTask';
    }
}
