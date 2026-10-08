<?php

namespace App\Planner\Filament\Livewire;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Pages\NevoboChanges;
use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Support\Tenancy\CurrentClub;
use Filament\Livewire\Topbar;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * The bar above the content, as the design system draws it: the match
 * search, the planning that is live, what needs attention and the account
 * menu. Rendered by the planner plugin inside the content column, so the
 * sidebar keeps the logo and the club switcher.
 */
class PlannerTopbar extends Topbar
{
    #[On('refresh-planner-topbar')]
    public function refreshPlanner(): void {}

    public function render(): View
    {
        return view('planner::filament.topbar', $this->viewData());
    }

    /** @return array{searchUrl: ?string, activeVersion: ?string, attention: int, attentionItems: list<array{label: string, count: int, url: string}>} */
    private function viewData(): array
    {
        if (!app(CurrentClub::class)->get()) {
            return ['searchUrl' => null, 'activeVersion' => null, 'attention' => 0, 'attentionItems' => []];
        }

        $canSearch = HomeMatches::canAccess();

        // What needs a decision, each with its own page; the bell counts
        // only what the user can open.
        $items = array_values(array_filter([
            ChangeRequestResource::canAccess() ? [
                'label' => __('Nieuwe wijzigingsverzoeken'),
                'count' => $this->openRequests(),
                'url' => ChangeRequestResource::getUrl('index'),
            ] : null,
            NevoboChanges::canAccess() ? [
                'label' => __('Open Nevobo-wijzigingen'),
                'count' => $this->openNevoboChanges(),
                'url' => NevoboChanges::getUrl(),
            ] : null,
        ], fn (?array $item): bool => $item !== null && $item['count'] > 0));

        return [
            'searchUrl' => $canSearch ? HomeMatches::getUrl(['zoeken' => 1]) : null,
            'activeVersion' => ScheduleVersion::query()
                ->where('status', ScheduleVersionStatus::Published)
                ->latest('published_at')
                ->value('name'),
            'attention' => array_sum(array_column($items, 'count')),
            'attentionItems' => $items,
        ];
    }

    private function openRequests(): int
    {
        if (!app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::ChangeRequests)) {
            return 0;
        }

        return ChangeRequest::query()->where('status', ChangeRequestStatus::Open)->count();
    }

    private function openNevoboChanges(): int
    {
        return FixtureMatch::query()
            ->whereHas('version', fn ($query) => $query->where('status', ScheduleVersionStatus::Published))
            ->whereIn('nevobo_change_status', NevoboChangeStatus::open())
            ->count();
    }
}
