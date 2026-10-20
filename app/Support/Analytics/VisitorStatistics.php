<?php

namespace App\Support\Analytics;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Visitor figures for a period of whole days ending today, compared with the
 * period of the same length right before it. A visit is a browser session
 * per day (the visit hash includes the date).
 */
final class VisitorStatistics
{
    public readonly Carbon $start;

    public function __construct(public readonly int $days)
    {
        $this->start = today()->subDays($days - 1);
    }

    /** @return array{visits: int, views: int, single_page_visits: int} */
    public function totals(): array
    {
        return $this->totalsBetween($this->start, now());
    }

    /** @return array{visits: int, views: int, single_page_visits: int} */
    public function previousTotals(): array
    {
        return $this->totalsBetween($this->start->copy()->subDays($this->days), $this->start->copy()->subSecond());
    }

    /**
     * Every day of the period, oldest first, including days without visits.
     *
     * @return Collection<int, array{date: Carbon, visits: int, views: int}>
     */
    public function timeline(): Collection
    {
        $daily = $this->query()
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as views, COUNT(DISTINCT visit_hash) as visits')
            ->groupBy('day')
            ->get()
            ->keyBy(fn (PageView $row): string => (string) $row->getAttribute('day'));

        return collect(range(0, $this->days - 1))->map(function (int $offset) use ($daily): array {
            $date = $this->start->copy()->addDays($offset);
            $row = $daily->get($date->toDateString());

            return [
                'date' => $date,
                'visits' => (int) ($row?->getAttribute('visits') ?? 0),
                'views' => (int) ($row?->getAttribute('views') ?? 0),
            ];
        });
    }

    /**
     * The most frequent values of a column, with their share of all page views.
     *
     * @param  'path'|'referrer_host'|'device_type'  $column
     * @return Collection<int, array{label: ?string, views: int, share: float}>
     */
    public function top(string $column, int $limit = 8): Collection
    {
        $total = max(1, $this->query()->count());

        return $this->query()
            ->selectRaw("{$column} as label, COUNT(*) as views")
            ->groupBy($column)
            ->orderByDesc('views')
            ->orderBy($column)
            ->limit($limit)
            ->get()
            ->map(fn (PageView $row): array => [
                'label' => $row->getAttribute('label'),
                'views' => (int) $row->getAttribute('views'),
                'share' => (int) $row->getAttribute('views') / $total,
            ]);
    }

    /** @return Builder<PageView> */
    private function query(): Builder
    {
        return PageView::query()->whereBetween('viewed_at', [$this->start, now()]);
    }

    /** @return array{visits: int, views: int, single_page_visits: int} */
    private function totalsBetween(Carbon $from, Carbon $until): array
    {
        $range = PageView::query()->whereBetween('viewed_at', [$from, $until]);
        $totals = (clone $range)->selectRaw('COUNT(*) as views, COUNT(DISTINCT visit_hash) as visits')->first();
        $singlePageVisits = DB::query()
            ->fromSub((clone $range)->select('visit_hash')->groupBy('visit_hash')->havingRaw('COUNT(*) = 1'), 'single_page')
            ->count();

        return [
            'visits' => (int) $totals?->getAttribute('visits'),
            'views' => (int) $totals?->getAttribute('views'),
            'single_page_visits' => $singlePageVisits,
        ];
    }
}
