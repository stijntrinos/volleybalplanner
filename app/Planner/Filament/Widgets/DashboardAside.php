<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Pages\NevoboChanges;
use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Planner\Filament\Widgets\Concerns\ResolvesDashboardVersion;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Services\ChangeRequestService;
use App\Planner\Support\DateFormat;
use App\Support\Tenancy\CurrentClub;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/**
 * The column next to the running changes: what the last Nevobo sync found
 * and the newest requests, which can be decided here directly.
 */
class DashboardAside extends Widget
{
    use HasPlannerWidget;
    use ResolvesDashboardVersion;

    private const REQUESTS_SHOWN = 3;

    protected static ?int $sort = 2;

    /** Rendered with the page: the dashboard is the first thing people see. */
    protected static bool $isLazy = false;

    // One column on small screens (the grid has one there), part of three on xl.
    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 1];

    protected string $view = 'planner::filament.widgets.dashboard-aside';

    public function decide(int $requestId, string $status): void
    {
        abort_unless($this->requestsEnabled(), 404);

        $decision = ChangeRequestStatus::tryFrom($status);
        $request = ChangeRequest::query()->find($requestId);

        if (!$request || !in_array($decision, [ChangeRequestStatus::Accepted, ChangeRequestStatus::Rejected, ChangeRequestStatus::Parked], true)) {
            return;
        }

        abort_unless(auth()->user()?->can('update', $request), 403);

        app(ChangeRequestService::class)->handle($request, $decision, null, auth()->id());

        Notification::make()
            ->title(match ($decision) {
                ChangeRequestStatus::Accepted => __('Verzoek geaccepteerd'),
                ChangeRequestStatus::Rejected => __('Verzoek afgewezen'),
                default => __('Verzoek geparkeerd'),
            })
            ->body(__('Pas de planning zelf aan; het verzoek is alleen afgehandeld.'))
            ->success()
            ->send();

        $this->dispatch('refresh-planner-topbar');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'sync' => $this->syncSummary(),
            'requests' => $this->requests(),
            'requestsUrl' => $this->requestsEnabled() && ChangeRequestResource::canAccess() ? ChangeRequestResource::getUrl('index') : null,
        ];
    }

    /** @return array{at: ?string, rows: list<array{count: int, label: string, tone: string, url: ?string, action: ?string}>} */
    private function syncSummary(): array
    {
        $version = $this->draftVersion();
        $matches = FixtureMatch::query()->where('version_id', $version?->getKey());
        $changesUrl = NevoboChanges::canAccess() ? NevoboChanges::getUrl() : null;

        $applied = (clone $matches)->where('nevobo_change_status', NevoboChangeStatus::Applied)->count();
        $conflicts = (clone $matches)->where('nevobo_change_status', NevoboChangeStatus::Conflict)->count();
        $new = (clone $matches)->where('created_at', '>=', now()->subDay())->whereNull('created_by')->count();
        $lastChange = (clone $matches)->max('nevobo_changed_at');

        return [
            'at' => $lastChange ? DateFormat::dateTime(Carbon::parse($lastChange)) : null,
            'rows' => [
                ['count' => $applied, 'label' => __('Tijden automatisch bijgewerkt'), 'tone' => $applied ? 'ok' : 'muted', 'url' => $applied ? $changesUrl : null, 'action' => $applied ? __('Bekijken') : null],
                ['count' => $conflicts, 'label' => __('Wijkt af van eigen planning'), 'tone' => $conflicts ? 'warn' : 'muted', 'url' => $conflicts ? $changesUrl : null, 'action' => $conflicts ? __('Beslissen') : null],
                ['count' => $new, 'label' => __('Nieuwe wedstrijden in de feed'), 'tone' => $new ? 'ok' : 'muted', 'url' => null, 'action' => null],
            ],
        ];
    }

    /** @return list<array{id: int, kind: string, when: string, title: string, reason: string, canDecide: bool}> */
    private function requests(): array
    {
        if (!$this->requestsEnabled()) {
            return [];
        }

        return ChangeRequest::query()
            ->where('status', ChangeRequestStatus::Open)
            ->with(['team', 'match'])
            ->latest('created_at')
            ->limit(self::REQUESTS_SHOWN)
            ->get()
            ->map(fn (ChangeRequest $request): array => [
                'id' => $request->getKey(),
                'kind' => $request->isDuty() ? __('Dienst wijzigen') : __('Wedstrijd verzetten'),
                'when' => $this->relativeMoment($request->created_at),
                'title' => $this->title($request),
                'reason' => $request->explanation ?: $request->reasonText(),
                'canDecide' => auth()->user()?->can('update', $request) ?? false,
            ])
            ->all();
    }

    private function title(ChangeRequest $request): string
    {
        if ($request->isDuty()) {
            return collect([$request->subjectDescription(), DateFormat::withWeekday($request->duty_date, short: true), $request->duty_time])
                ->filter()->implode(' · ');
        }

        return collect([$request->subjectDescription(), DateFormat::withWeekday($request->match?->date, short: true)])
            ->filter()->implode(' · ');
    }

    private function relativeMoment(?Carbon $moment): string
    {
        if (!$moment) {
            return '';
        }

        return match (true) {
            $moment->isToday() => __('vandaag :time', ['time' => $moment->format('H:i')]),
            $moment->isYesterday() => __('gisteren :time', ['time' => $moment->format('H:i')]),
            default => DateFormat::dateTime($moment) ?? '',
        };
    }

    /** Requests show (and can be decided) only with the module on and the right to list them. */
    private function requestsEnabled(): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::ChangeRequests)
            && auth()->user()?->can('viewAny', ChangeRequest::class);
    }
}
