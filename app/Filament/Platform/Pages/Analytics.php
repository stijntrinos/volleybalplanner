<?php

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Widgets\VisitorOverview;
use App\Models\PageView;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Analytics extends Page
{
    protected string $view = 'filament.platform.pages.analytics';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    public string $period = '30';

    public static function getNavigationLabel(): string
    {
        return __('Bezoekersstatistieken');
    }

    public function getTitle(): string
    {
        return __('Bezoekersstatistieken');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isPlatformAdmin() ?? false;
    }

    protected function getHeaderWidgets(): array
    {
        return [VisitorOverview::class];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $days = match ($this->period) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };
        $start = today()->subDays($days - 1);
        $query = PageView::query()->whereBetween('viewed_at', [$start, now()]);
        $daily = (clone $query)
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as views, COUNT(DISTINCT visit_hash) as visits')
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');
        $timeline = collect(range(0, $days - 1))->map(function (int $offset) use ($start, $daily): array {
            $date = $start->copy()->addDays($offset);
            $row = $daily->get($date->toDateString());

            return ['date' => $date->format('d-m'), 'views' => (int) ($row?->views ?? 0), 'visits' => (int) ($row?->visits ?? 0)];
        });

        return [
            'days' => $days,
            'timeline' => $timeline,
            'maxViews' => max(1, $timeline->max('views')),
            'pages' => (clone $query)->selectRaw('path, COUNT(*) as views')->groupBy('path')->orderByDesc('views')->orderBy('path')->limit(10)->get(),
            'referrers' => (clone $query)->selectRaw('referrer_host, COUNT(*) as views')->groupBy('referrer_host')->orderByDesc('views')->orderBy('referrer_host')->limit(10)->get(),
            'devices' => (clone $query)->selectRaw('device_type, COUNT(*) as views')->groupBy('device_type')->orderByDesc('views')->get(),
        ];
    }
}
