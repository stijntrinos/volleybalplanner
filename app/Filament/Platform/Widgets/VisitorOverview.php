<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Pages\Analytics;
use App\Support\Analytics\VisitorStatistics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class VisitorOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return Auth::user()?->isPlatformAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $statistics = new VisitorStatistics(30);
        $timeline = $statistics->timeline();
        $totals = $statistics->totals();
        $url = Analytics::getUrl(panel: 'platform');

        return [
            Stat::make(__('Bezoeken vandaag'), number_format($timeline->last()['visits'], 0, ',', '.'))
                ->description(trans_choice(':count paginaweergave|:count paginaweergaven', $timeline->last()['views']))
                ->url($url),
            Stat::make(__('Bezoeken · 30 dagen'), number_format($totals['visits'], 0, ',', '.'))
                ->description(__('Browsersessies per dag, met toestemming'))
                ->chart($timeline->pluck('visits')->all())
                ->color('primary')
                ->url($url),
            Stat::make(__('Paginaweergaven · 30 dagen'), number_format($totals['views'], 0, ',', '.'))
                ->chart($timeline->pluck('views')->all())
                ->color('primary')
                ->url($url),
        ];
    }
}
