<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Widgets\Concerns\ResolvesDashboardVersion;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ConflictEngine;
use App\Planner\Support\DateFormat;
use Filament\Widgets\Widget;

/**
 * The tinted bar at the top of the dashboard: which version is live since
 * when, and how far the newest draft has drifted from it.
 */
class VersionBanner extends Widget
{
    use HasPlannerWidget;
    use ResolvesDashboardVersion;

    protected static ?int $sort = -3;

    /** Rendered with the page: the dashboard is the first thing people see. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'planner::filament.widgets.version-banner';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $published = $this->publishedVersion();
        $draft = $this->draftVersion();

        return [
            'published' => $published,
            'publishedAt' => DateFormat::dateTime($published?->published_at),
            'draft' => $draft,
            'changes' => $published && $draft ? $this->changedMatches($published, $draft) : null,
            'conflicts' => $draft ? $this->hardConflicts($draft) : null,
            'draftUrl' => $draft && HomeMatches::canAccess() ? HomeMatches::getUrl() : null,
        ];
    }

    /**
     * Matches of the draft whose moment or place differs from the published
     * version, or that are not in it at all. Compared by match code: a
     * duplicated version gets new ids.
     */
    private function changedMatches(ScheduleVersion $published, ScheduleVersion $draft): int
    {
        $fingerprint = fn (FixtureMatch $match): string => implode('|', [
            $match->date?->toDateString(), FixtureMatch::shortTime($match->time), $match->venue_id, $match->court_id,
        ]);
        $columns = ['match_code', 'date', 'time', 'venue_id', 'court_id'];

        $live = $published->matches()->get($columns)->mapWithKeys(fn (FixtureMatch $match): array => [$match->match_code => $fingerprint($match)]);

        return $draft->matches()->get($columns)
            ->filter(fn (FixtureMatch $match): bool => ($live[$match->match_code] ?? null) !== $fingerprint($match))
            ->count();
    }

    private function hardConflicts(ScheduleVersion $draft): int
    {
        return collect(app(ConflictEngine::class)->scanVersion($draft))
            ->where('level', ConflictEngine::LEVEL_CONFLICT)
            ->pluck('subject_match_id')
            ->unique()
            ->count();
    }
}
