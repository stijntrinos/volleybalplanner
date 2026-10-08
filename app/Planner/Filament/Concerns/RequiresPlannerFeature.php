<?php

namespace App\Planner\Filament\Concerns;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;

/**
 * Core planner components need no module; components of an optional module
 * override requiredFeatures() and disappear (and refuse direct URLs) when the
 * club has that module switched off.
 */
trait RequiresPlannerFeature
{
    /** @return list<PlannerFeature> */
    protected static function requiredFeatures(): array
    {
        return [];
    }

    /**
     * The module the club's plan lacks for this component, or null when it is
     * available (or there is no club). The navigation shows such components
     * locked, with an upgrade prompt.
     */
    public static function lockedFeature(): ?PlannerFeature
    {
        $club = app(CurrentClub::class)->get();

        if ($club === null) {
            return null;
        }

        foreach (static::requiredFeatures() as $feature) {
            if (!$club->hasFeature($feature)) {
                return $feature;
            }
        }

        return null;
    }

    protected static function plannerFeaturesEnabled(): bool
    {
        $club = app(CurrentClub::class)->get();

        if ($club === null) {
            return false;
        }

        foreach (static::requiredFeatures() as $feature) {
            if (!$club->hasFeature($feature)) {
                return false;
            }
        }

        return true;
    }
}
