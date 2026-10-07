<?php

namespace App\Planner\Policies;

class CalendarEventPolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'CalendarEvent';
    }
}
