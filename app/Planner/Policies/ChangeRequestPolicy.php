<?php

namespace App\Planner\Policies;

class ChangeRequestPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'ChangeRequest';
    }
}
