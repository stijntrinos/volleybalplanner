<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Pages\Analytics;
use App\Models\PageView;
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
        $stats = PageView::query()
            ->whereBetween('viewed_at', [today()->subDays(29), now()])
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT visit_hash) as visits, SUM(CASE WHEN viewed_at >= ? THEN 1 ELSE 0 END) as today_views', [today()])
            ->first();

        return [
            Stat::make(__('Paginaweergaven vandaag'), number_format((int) $stats->today_views, 0, ',', '.'))
                ->url(Analytics::getUrl(panel: 'platform')),
            Stat::make(__('Paginaweergaven · 30 dagen'), number_format((int) $stats->views, 0, ',', '.'))
                ->url(Analytics::getUrl(panel: 'platform')),
            Stat::make(__('Bezoeken · 30 dagen'), number_format((int) $stats->visits, 0, ',', '.'))
                ->description(__('Browsersessies per dag, met toestemming'))
                ->url(Analytics::getUrl(panel: 'platform')),
        ];
    }
}
