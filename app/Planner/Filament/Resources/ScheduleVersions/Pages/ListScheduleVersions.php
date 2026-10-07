<?php

namespace App\Planner\Filament\Resources\ScheduleVersions\Pages;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListScheduleVersions extends ListRecords
{
    protected static string $resource = ScheduleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Nieuwe planningversie'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }

    /**
     * The overview behind a rejected publication. The confirmation closes and
     * this takes its place: per match it lists what blocks publishing,
     * instead of one generic error message.
     */
    public function publishConflictsAction(): Action
    {
        return Action::make('publishConflicts')
            ->modalHeading(__('Publiceren is geblokkeerd'))
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalDescription(fn (array $arguments): string => trans_choice(
                '":version" bevat :count hard conflict dat eerst opgelost moet worden. Losse waarschuwingen staan hier niet bij.|":version" bevat :count harde conflicten die eerst opgelost moeten worden. Losse waarschuwingen staan hier niet bij.',
                count($arguments['conflicts'] ?? []),
                ['version' => $arguments['version'] ?? __('Deze versie'), 'count' => count($arguments['conflicts'] ?? [])],
            ))
            ->modalContent(fn (array $arguments): View => view(
                'planner::filament.resources.schedule-versions.publish-conflicts',
                ['groups' => $this->conflictsPerMatch($arguments['conflicts'] ?? [])],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'));
    }

    /**
     * Groups the scan result per match, so the overview names the placement
     * once with everything that goes wrong below it.
     *
     * @param  list<array<string, mixed>>  $conflicts
     * @return list<array{match: ?FixtureMatch, messages: list<string>}>
     */
    private function conflictsPerMatch(array $conflicts): array
    {
        $matches = FixtureMatch::query()
            ->whereKey(collect($conflicts)->pluck('subject_match_id')->filter()->unique())
            ->with(['venue', 'court', 'secondCourt'])
            ->get()->keyBy('id');

        return collect($conflicts)
            ->groupBy('subject_match_id')
            ->map(fn (Collection $group, int|string $matchId): array => [
                'match' => $matches->get((int) $matchId),
                'messages' => $group->pluck('message')->unique()->values()->all(),
            ])
            ->sortBy(fn (array $group): string => ($group['match']?->date?->toDateString() ?? '9999-12-31')
                .' '.substr((string) $group['match']?->time, 0, 5))
            ->values()->all();
    }

    public function getSubheading(): ?string
    {
        return __('Beheer concepten, kies welke planning actief is en archiveer versies die niet meer nodig zijn.');
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('Alle versies'))
                ->badge(ScheduleVersion::query()->count()),
            'draft' => Tab::make(__('Concepten'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->badge(ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ScheduleVersionStatus::Draft)),
            'published' => Tab::make(__('Actieve planning'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->badge(ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ScheduleVersionStatus::Published)),
            'archived' => Tab::make(__('Archief'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->badge(ScheduleVersion::query()->where('status', ScheduleVersionStatus::Archived)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', ScheduleVersionStatus::Archived)),
        ];
    }
}
