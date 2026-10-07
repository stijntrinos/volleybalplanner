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
