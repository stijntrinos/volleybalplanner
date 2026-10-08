<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Widgets\Concerns\ResolvesDashboardVersion;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Support\Tenancy\CurrentClub;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Four tiles: what is coming up this week and what still needs a hand.
 */
class PlannerStats extends Widget
{
    use HasPlannerWidget;
    use ResolvesDashboardVersion;

    protected static ?int $sort = -2;

    /** Rendered with the page: the dashboard is the first thing people see. */
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'planner::filament.widgets.planner-stats';

    /** @return array{stats: list<array{label: string, value: int, note: string, warn: bool}>} */
    protected function getViewData(): array
    {
        $version = $this->liveVersion();
        $week = $this->homeMatchesThisWeek($version?->getKey());

        $venues = (clone $week)->distinct()->count('venue_id');
        $matches = (clone $week)->count();

        $missingReferees = (clone $week)->whereNull('referee')->where('nevobo_referee', false)->count();
        $missingScorers = (clone $week)->whereNull('scorer')->count();

        $changes = FixtureMatch::query()
            ->where('version_id', $version?->getKey())
            ->where('match_type', MatchType::Home)
            ->where(fn (Builder $query) => $query
                ->whereIn('change_status', [ChangeStatus::Requested, ChangeStatus::ToChange])
                ->orWhereIn('nevobo_change_status', NevoboChangeStatus::open()));
        $toMove = (clone $changes)->where('change_status', ChangeStatus::ToChange)->count();
        $changeCount = $changes->count();

        $stats = [
            [
                'label' => __('Thuiswedstrijden komende 7 dagen'),
                'value' => $matches,
                'note' => trans_choice('In :count sporthal|In :count sporthallen', $venues),
                'warn' => false,
            ],
            [
                'label' => __('Diensten zonder team'),
                'value' => $missingReferees + $missingScorers,
                'note' => $missingReferees + $missingScorers === 0
                    ? __('Alles bezet')
                    : collect([
                        $missingReferees ? trans_choice(':count scheidsrechter|:count scheidsrechters', $missingReferees) : null,
                        $missingScorers ? trans_choice(':count teller|:count tellers', $missingScorers) : null,
                    ])->filter()->implode(', '),
                'warn' => $missingReferees + $missingScorers > 0,
            ],
            [
                'label' => __('Lopende wijzigingen'),
                'value' => $changeCount,
                'note' => $toMove > 0
                    ? trans_choice(':count nog te verplaatsen|:count nog te verplaatsen', $toMove)
                    : __('Niets te verplaatsen'),
                'warn' => $toMove > 0,
            ],
        ];

        if (app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::ChangeRequests)) {
            $open = ChangeRequest::query()->where('status', ChangeRequestStatus::Open)->count();
            $parked = ChangeRequest::query()->where('status', ChangeRequestStatus::Parked)->count();

            $stats[] = [
                'label' => __('Nieuwe verzoeken'),
                'value' => $open,
                'note' => $parked > 0 ? trans_choice(':count geparkeerd|:count geparkeerd', $parked) : __('Niets geparkeerd'),
                'warn' => false,
            ];
        }

        return ['stats' => $stats];
    }

    /** @return Builder<FixtureMatch> */
    private function homeMatchesThisWeek(?int $versionId): Builder
    {
        return FixtureMatch::query()
            ->where('version_id', $versionId)
            ->where('match_type', MatchType::Home)
            ->whereBetween('date', [now()->toDateString(), now()->addDays(6)->toDateString()]);
    }
}
