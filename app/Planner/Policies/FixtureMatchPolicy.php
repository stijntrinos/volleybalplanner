<?php

namespace App\Planner\Policies;

class FixtureMatchPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'FixtureMatch';
    }
}
