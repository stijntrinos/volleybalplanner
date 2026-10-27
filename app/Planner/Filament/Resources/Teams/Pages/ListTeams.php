<?php

namespace App\Planner\Filament\Resources\Teams\Pages;

use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Teams\TeamResource;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\PointsListImport;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListTeams extends ListRecords
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPointsList')
                ->label(__('Puntenlijst importeren'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->visible(fn (): bool => (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::VolunteerPoints))
                ->authorize(fn (): bool => auth()->user()?->can('Update:Team') ?? false)
                ->modalHeading(__('Puntenlijst van de vrijwilligerscommissie'))
                ->modalDescription(__('Plak de lijst uit de spreadsheet, met per team: team, behoefte, geleverd, nog in te vullen, percentage en minimumpercentage. Een kopregel mag; zonder kopregel geldt deze volgorde. De planner neemt behoefte, geleverd en minimumpercentage over en rekent de rest zelf uit.'))
                ->schema([
                    Textarea::make('list')
                        ->label(__('Lijst'))
                        ->rows(12)
                        ->required()
                        ->maxLength(100_000)
                        ->placeholder("Team\tBehoefte\tGeleverd\tNog in te vullen\tPercentage\tMinimumpercentage\nDS 1\t312\t137\t61\t72,0%\t70"),
                ])
                ->action(function (array $data): void {
                    $outcome = app(PointsListImport::class)->import((string) $data['list']);
                    $lines = array_filter([
                        $outcome['updated']
                            ? trans_choice(':count team bijgewerkt.|:count teams bijgewerkt.', count($outcome['updated']))
                            : __('Geen teams bijgewerkt.'),
                        $outcome['unknown'] ? __('Niet gevonden: :teams.', ['teams' => implode(', ', $outcome['unknown'])]) : null,
                        ...$outcome['errors'],
                    ]);

                    Notification::make()
                        ->title(__('Puntenlijst ingelezen'))
                        ->body(implode("\n", $lines))
                        ->color($outcome['unknown'] || $outcome['errors'] ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                })
                ->modalSubmitActionLabel(__('Importeren')),
            CreateAction::make()->label(__('Team toevoegen'))->icon(Heroicon::OutlinedPlus)
                ->hidden(fn (): bool => PlanLimits::reached(PlanLimit::Teams)),
            PlanLimits::lockedAction(PlanLimit::Teams, 'createLocked', __('Team toevoegen')),
        ];
    }

    public function getSubheading(): ?string
    {
        return $this->hasDuties()
            ? __('Beheer standaardplanning, wedstrijdtaken, FTZ-deelname en externe wedstrijdbronnen per team.')
            : __('Beheer standaardplanning en externe wedstrijdbronnen per team.');
    }

    public function getTabs(): array
    {
        $needsAttention = fn (Builder $query): Builder => $query->where(function (Builder $query): void {
            $query->whereNull('default_weekday')
                ->orWhereNull('default_time')
                ->orWhereNull('default_venue_id');
        });

        $tabs = [
            'all' => Tab::make(__('Alle teams'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->badge(Team::query()->count()),
        ];

        // The duty-related tabs only mean something with the duties module.
        if ($this->hasDuties()) {
            $tabs += [
                'duties' => Tab::make(__('FTZ-planning'))
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->badge(Team::query()->where('include_in_duties', true)->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('include_in_duties', true)),
                'own_scorer' => Tab::make(__('Eigen teller'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->badge(Team::query()->where('scores_own_match', true)->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('scores_own_match', true)),
                'nevobo' => Tab::make(__('Nevobo-scheidsrechter'))
                    ->icon(Heroicon::OutlinedFlag)
                    ->badge(Team::query()->where('provides_referee', true)->count())
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('provides_referee', true)),
            ];
        }

        $tabs['attention'] = Tab::make(__('Aandacht nodig'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->badge($needsAttention(Team::query())->count())
            ->badgeColor('warning')
            ->modifyQueryUsing($needsAttention);

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    private function hasDuties(): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::Duties);
    }
}
