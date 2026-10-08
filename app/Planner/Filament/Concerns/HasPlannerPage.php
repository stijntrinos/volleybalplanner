<?php

namespace App\Planner\Filament\Concerns;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;

/**
 * Access for planner pages: the Shield page permission (View:{Page}) plus the
 * club's module switch. Filament aborts with a 403 when canAccess() is false,
 * so this also blocks direct URLs.
 */
trait HasPlannerPage
{
    use HasPageShield {
        canAccess as protected canAccessViaShield;
    }
    use RequiresPlannerFeature;

    public static function canAccess(): bool
    {
        return static::plannerFeaturesEnabled() && static::canAccessViaShield();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess() && parent::shouldRegisterNavigation();
    }

    /** Locked by the plan, but the user would see it otherwise. */
    public static function showsLockedNavigation(): bool
    {
        return static::lockedFeature() !== null && static::canAccessViaShield();
    }
}
