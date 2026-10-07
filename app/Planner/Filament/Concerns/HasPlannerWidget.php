<?php

namespace App\Planner\Filament\Concerns;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;

/**
 * Widgets: the Shield widget permission (View:{Widget}) plus the module switch.
 */
trait HasPlannerWidget
{
    use HasWidgetShield {
        canView as protected canViewViaShield;
    }
    use RequiresPlannerFeature;

    public static function canView(): bool
    {
        return static::plannerFeaturesEnabled() && static::canViewViaShield();
    }
}
