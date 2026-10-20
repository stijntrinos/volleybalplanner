<?php

namespace App\Filament\Platform\Pages;

use App\Support\Analytics\VisitorStatistics;
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

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $days = match ($this->period) {
            '7' => 7,
            '90' => 90,
            default => 30,
        };
        $statistics = new VisitorStatistics($days);
        $totals = $statistics->totals();
        $previous = $statistics->previousTotals();
        $timeline = $statistics->timeline();

        return [
            'days' => $days,
            'kpis' => $this->kpis($totals, $previous),
            'timeline' => $timeline,
            'scaleMax' => $this->niceCeiling($timeline->max('visits')),
            'labelEvery' => match ($days) {
                7 => 1,
                30 => 5,
                default => 14,
            },
            'lists' => [
                ['heading' => __('Populaire pagina’s'), 'rows' => $statistics->top('path'), 'column' => 'path'],
                ['heading' => __('Herkomst'), 'rows' => $statistics->top('referrer_host'), 'column' => 'referrer_host'],
                ['heading' => __('Apparaten'), 'rows' => $statistics->top('device_type', 3), 'column' => 'device_type'],
            ],
        ];
    }

    /**
     * @param  array{visits: int, views: int, single_page_visits: int}  $totals
     * @param  array{visits: int, views: int, single_page_visits: int}  $previous
     * @return list<array{label: string, value: string, change: ?float, higherIsBetter: bool, hint: string}>
     */
    private function kpis(array $totals, array $previous): array
    {
        $pagesPerVisit = fn (array $figures): float => $figures['visits'] > 0 ? $figures['views'] / $figures['visits'] : 0.0;
        $singlePageShare = fn (array $figures): float => $figures['visits'] > 0 ? $figures['single_page_visits'] / $figures['visits'] : 0.0;
        $change = fn (float $now, float $before): ?float => $before > 0 ? ($now - $before) / $before : null;

        return [
            [
                'label' => __('Bezoeken'),
                'value' => number_format($totals['visits'], 0, ',', '.'),
                'change' => $change($totals['visits'], $previous['visits']),
                'higherIsBetter' => true,
                'hint' => __('Browsersessies per dag'),
            ],
            [
                'label' => __('Paginaweergaven'),
                'value' => number_format($totals['views'], 0, ',', '.'),
                'change' => $change($totals['views'], $previous['views']),
                'higherIsBetter' => true,
                'hint' => __('Alle bekeken pagina’s'),
            ],
            [
                'label' => __('Pagina’s per bezoek'),
                'value' => number_format($pagesPerVisit($totals), 1, ',', '.'),
                'change' => $change($pagesPerVisit($totals), $pagesPerVisit($previous)),
                'higherIsBetter' => true,
                'hint' => __('Gemiddeld per bezoek'),
            ],
            [
                'label' => __('Eén pagina bekeken'),
                'value' => number_format($singlePageShare($totals) * 100, 0, ',', '.').'%',
                'change' => $change($singlePageShare($totals), $singlePageShare($previous)),
                'higherIsBetter' => false,
                'hint' => __('Bezoeken die na één pagina stopten'),
            ],
        ];
    }

    /** Rounds the chart's top up to a value whose half is a round gridline. */
    private function niceCeiling(int $value): int
    {
        if ($value < 10) {
            return max(4, $value + $value % 2);
        }

        $magnitude = 10 ** (int) floor(log10($value));
        foreach ([1, 2, 3, 4, 5, 6, 8, 10] as $step) {
            if ($step * $magnitude >= $value) {
                return $step * $magnitude;
            }
        }

        return 10 * $magnitude;
    }
}
