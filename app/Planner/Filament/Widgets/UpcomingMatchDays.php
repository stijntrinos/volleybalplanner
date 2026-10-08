<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Widgets\Concerns\ResolvesDashboardVersion;
use App\Planner\Models\FixtureMatch;
use App\Planner\Support\DateFormat;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The next home match days as tiles, each with where it is played and
 * whether everything is staffed. A tile opens the day planner on that day.
 */
class UpcomingMatchDays extends Widget
{
    use HasPlannerWidget;
    use ResolvesDashboardVersion;

    private const DAYS_SHOWN = 4;

    protected static ?int $sort = 4;

    /** Rendered with the page: the dashboard is the first thing people see. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'planner::filament.widgets.upcoming-match-days';

    /** @return array{days: list<array{date: string, day: string, where: string, note: string, warn: bool, url: ?string}>} */
    protected function getViewData(): array
    {
        $version = $this->liveVersion();

        $matches = FixtureMatch::query()
            ->where('version_id', $version?->getKey())
            ->where('match_type', MatchType::Home)
            ->where('date', '>=', now()->toDateString())
            ->with('venue:id,name')
            ->orderBy('date')
            ->get(['id', 'date', 'venue_id', 'referee', 'nevobo_referee', 'scorer', 'change_status']);

        $canOpen = HomeMatches::canAccess();

        $days = $matches
            ->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString())
            ->take(self::DAYS_SHOWN)
            ->map(fn (Collection $day, string $date): array => $this->day($date, $day, $canOpen))
            ->values()
            ->all();

        return ['days' => $days];
    }

    /** @param  Collection<int, FixtureMatch>  $matches */
    private function day(string $date, Collection $matches, bool $canOpen): array
    {
        $open = $matches->filter(fn (FixtureMatch $match): bool => ($match->referee === null && ! $match->nevobo_referee) || $match->scorer === null)->count();
        $toMove = $matches->where('change_status', ChangeStatus::ToChange)->count();

        $issues = collect([
            $open ? trans_choice(':count dienst open|:count diensten open', $open) : null,
            $toMove ? trans_choice(':count te verplaatsen|:count te verplaatsen', $toMove) : null,
        ])->filter();

        return [
            'date' => $date,
            'day' => (string) DateFormat::withWeekday($date, short: true),
            'where' => $matches->pluck('venue.name')->filter()->unique()->sort()->implode(' · '),
            'note' => trans_choice(':count wedstrijd|:count wedstrijden', $matches->count()).' · '
                .($issues->isEmpty() ? __('alles bezet') : $issues->implode(' · ')),
            'warn' => $issues->isNotEmpty(),
            'url' => $canOpen ? HomeMatches::getUrl(['datum' => $date]) : null,
        ];
    }
}
