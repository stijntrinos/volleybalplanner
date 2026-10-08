<?php

namespace App\Planner\Filament;

use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Planner\Filament\Resources\HallDuties\HallDutyResource;
use App\Planner\Filament\Resources\Trainings\TrainingResource;
use App\Planner\Filament\Resources\Volleystars\VolleystarsBlockResource;
use Filament\Navigation\NavigationItem;

/**
 * Modules outside the club's plan stay in the navigation, greyed out with a
 * lock. Their link only opens the upgrade prompt (planner-upgrade event);
 * the pages themselves keep refusing access.
 */
class LockedModuleNavigation
{
    /** @var list<class-string> pages and resources of optional modules */
    public const COMPONENTS = [
        AwayMatches::class,
        DutyPlanner::class,
        HallDutyResource::class,
        VolleystarsBlockResource::class,
        DutiesDashboard::class,
        ChangeRequestResource::class,
        TrainingResource::class,
    ];

    public const URL_PREFIX = '#upgrade:';

    /** @return list<NavigationItem> */
    public static function items(): array
    {
        return array_map(fn (string $component): NavigationItem => NavigationItem::make($component)
            ->label(fn (): string => $component::getNavigationLabel())
            ->group(fn (): mixed => $component::getNavigationGroup())
            ->sort(fn (): ?int => $component::getNavigationSort())
            ->icon('heroicon-o-lock-closed')
            ->url(fn (): string => self::URL_PREFIX.$component::lockedFeature()?->value)
            ->isActiveWhen(fn (): bool => false)
            ->visible(fn (): bool => $component::showsLockedNavigation()), self::COMPONENTS);
    }
}
