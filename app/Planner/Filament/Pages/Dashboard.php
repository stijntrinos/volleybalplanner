<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Filament\Widgets\DashboardAside;
use App\Planner\Filament\Widgets\PendingAwayChanges;
use App\Planner\Filament\Widgets\PendingChanges;
use App\Planner\Filament\Widgets\PlannerStats;
use App\Planner\Filament\Widgets\UpcomingMatchDays;
use App\Planner\Filament\Widgets\VersionBanner;
use App\Planner\Jobs\SyncNevoboPlanningJob;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * The club dashboard as the design system draws it: what is live, what
 * needs attention and the coming match days. Widgets are listed here
 * instead of discovered, so their order and width are fixed.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected Width|string|null $maxContentWidth = 'full';

    /** @return list<class-string> */
    public function getWidgets(): array
    {
        return [
            VersionBanner::class,
            PlannerStats::class,
            PendingChanges::class,
            DashboardAside::class,
            PendingAwayChanges::class,
            UpcomingMatchDays::class,
        ];
    }

    /** @return array<string, int> */
    public function getColumns(): int|array
    {
        return ['default' => 1, 'xl' => 3];
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNevobo')
                ->label(__('Nevobo nu vergelijken'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => NevoboChanges::canAccess())
                ->action(function (): void {
                    $club = app(CurrentClub::class)->get();

                    if ($club) {
                        SyncNevoboPlanningJob::dispatch($club);
                    }

                    Notification::make()
                        ->title(__('Nevobo-vergelijking gestart'))
                        ->body(__('De wijzigingen verschijnen bij Nevobo-wijzigingen zodra de feeds zijn opgehaald.'))
                        ->success()
                        ->send();
                }),
            Action::make('dayPlanner')
                ->label(__('Naar dagrooster'))
                ->visible(fn (): bool => HomeMatches::canAccess())
                ->url(fn (): string => HomeMatches::getUrl()),
        ];
    }
}
