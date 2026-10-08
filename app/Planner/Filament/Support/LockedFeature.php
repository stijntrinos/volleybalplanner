<?php

namespace App\Planner\Filament\Support;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Filament\Support\Icons\Heroicon;

/**
 * A module outside the club's plan, shown where it would be used: greyed out
 * with an upgrade button that opens the upgrade prompt.
 */
final class LockedFeature
{
    public static function isLocked(PlannerFeature $feature): bool
    {
        $club = app(CurrentClub::class)->get();

        return $club !== null && !$club->hasFeature($feature);
    }

    /** "Upgrade" next to a field label; only when the module is locked. */
    public static function hintAction(PlannerFeature $feature): Action
    {
        return self::upgradeAction($feature, 'hint')
            ->link()
            ->size('sm')
            ->visible(fn (): bool => self::isLocked($feature));
    }

    /**
     * A short notice with the upgrade button, for a section whose module is
     * locked.
     *
     * @return list<Component>
     */
    public static function notice(PlannerFeature $feature): array
    {
        $plan = app(CurrentClub::class)->get()?->upgradePlanName($feature);

        return [
            Text::make($plan
                ? __(':module zit niet in je pakket; met :plan krijg je het erbij.', ['module' => $feature->getLabel(), 'plan' => $plan])
                : __(':module zit niet in je pakket.', ['module' => $feature->getLabel()])),
            Actions::make([self::upgradeAction($feature, 'notice')->color('gray')]),
        ];
    }

    private static function upgradeAction(PlannerFeature $feature, string $suffix): Action
    {
        return Action::make("upgrade_{$feature->value}_{$suffix}")
            ->label(__('Upgrade'))
            ->icon(Heroicon::OutlinedLockClosed)
            ->alpineClickHandler("\$dispatch('planner-upgrade', { feature: '{$feature->value}' })");
    }
}
