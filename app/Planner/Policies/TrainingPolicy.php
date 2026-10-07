<?php

namespace App\Planner\Policies;

class TrainingPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Training';
    }
}
