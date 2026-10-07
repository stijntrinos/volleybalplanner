<?php

namespace App\Planner\Policies;

/**
 * Marking a Nevobo change as processed is bookkeeping on the match it belongs
 * to: whoever may update the match may tick it off. A separate right would mean
 * granting two things for one action.
 */
class NevoboChangePolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'FixtureMatch';
    }
}
