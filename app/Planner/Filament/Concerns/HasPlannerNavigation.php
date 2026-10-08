<?php

namespace App\Planner\Filament\Concerns;

/**
 * Access for planner resources: the policy (Shield permission) plus the
 * club's module switch. Blocking canAccess() also blocks direct URLs, not
 * just the navigation entry.
 */
trait HasPlannerNavigation
{
    use RequiresPlannerFeature;

    public static function canAccess(): bool
    {
        return static::plannerFeaturesEnabled() && parent::canAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Locked by the plan, but the user would see it otherwise. */
    public static function showsLockedNavigation(): bool
    {
        return static::lockedFeature() !== null && parent::canAccess();
    }
}
