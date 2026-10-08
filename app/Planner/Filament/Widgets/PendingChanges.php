<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Filament\Concerns\HasPlannerWidget;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Widgets\Concerns\ResolvesWidgetVersion;
use App\Planner\Models\FixtureMatch;
use App\Planner\Services\PlannerService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Home matches with a running change: requested or to be moved by us, or
 * moved by Nevobo and not yet reviewed.
 */
class PendingChanges extends TableWidget
{
    use HasPlannerWidget;
    use ResolvesWidgetVersion;

    protected static ?int $sort = 1;

    /** Rendered with the page: the dashboard is the first thing people see. */
    protected static bool $isLazy = false;

    // One column on small screens (the grid has one there), part of three on xl.
    protected int|string|array $columnSpan = ['default' => 1, 'xl' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'planner-widget'])
            ->striped()
            ->heading(__('Lopende wijzigingen'))
            ->description(__('Wedstrijden waarvoor een wijziging loopt of die Nevobo heeft verzet.'))
            ->query($this->matches())
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->emptyStateHeading(__('Geen lopende wijzigingen'))
            ->emptyStateDescription(__('Verzette wedstrijden en wijzigingen vanuit Nevobo verschijnen hier.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (FixtureMatch $record): ?string => self::matchUrl($record))
            ->columns([
                TextColumn::make('date')
                    ->label(__('Datum'))
                    ->date('d-m-Y')
                    ->placeholder(__('Nog geen datum'))
                    ->sortable(),

                TextColumn::make('time')
                    ->label(__('Tijd'))
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : '—'),

                TextColumn::make('match_code')
                    ->label(__('Code'))
                    ->searchable(),

                // For an away match the opponent is the home team in the row, so
                // both teams are shown side by side.
                TextColumn::make('home_team_name')
                    ->label(__('Wedstrijd'))
                    ->state(fn (FixtureMatch $record): string => trim($record->home_team_name.' - '.$record->away_team_name, ' -'))
                    ->searchable(['home_team_name', 'away_team_name']),

                TextColumn::make('match_type')
                    ->label(__('Type'))
                    ->badge()
                    ->formatStateUsing(fn (?MatchType $state): string => $state === MatchType::Home ? __('Thuis') : __('Uit'))
                    ->color(fn (?MatchType $state): string => $state === MatchType::Home ? 'primary' : 'gray'),

                TextColumn::make('change_status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (FixtureMatch $record): string => $record->nevobo_change_status === NevoboChangeStatus::Conflict ? 'danger' : 'warning')
                    ->description(fn (FixtureMatch $record): ?string => match (true) {
                        (bool) $record->nevobo_changed_at => $this->nevoboSummary($record),
                        // The date in the row is where it is now; the proposal still awaits approval.
                        $record->hasProvisionalPlacement() => __('Voorstel: :moment', ['moment' => $record->provisional_date->format('d-m-Y')
                            .($record->provisional_time ? ' '.FixtureMatch::shortTime($record->provisional_time) : '')]),
                        default => null,
                    })
                    ->state(fn (FixtureMatch $record): string => match (true) {
                        $record->nevobo_change_status === NevoboChangeStatus::Conflict => __('Actie nodig'),
                        $record->nevobo_change_status === NevoboChangeStatus::Applied => __('Automatisch bijgewerkt'),
                        $record->change_status === ChangeStatus::Requested => __('Wijziging aangevraagd'),
                        $record->change_status === ChangeStatus::ToChange => __('Moet verplaatst worden'),
                        default => __('Wijziging loopt'),
                    }),
            ])
            ->recordActions([
                Action::make('acceptNevobo')
                    ->label(__('Nevobo overnemen'))
                    ->icon('heroicon-m-arrow-down-on-square')
                    ->requiresConfirmation()
                    ->modalDescription(__('De planning van deze wedstrijd wordt vervangen door wat Nevobo doorgeeft.'))
                    ->authorize(fn (FixtureMatch $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (FixtureMatch $record): bool => $record->nevobo_change_status === NevoboChangeStatus::Conflict)
                    ->action(function (FixtureMatch $record): void {
                        app(PlannerService::class)->acceptNevoboChange($record, auth()->id());
                        Notification::make()->title(__('Wijziging van Nevobo overgenomen'))->success()->send();
                    }),

                Action::make('ignoreNevobo')
                    ->label(fn (FixtureMatch $record): string => $record->nevobo_change_status === NevoboChangeStatus::Conflict
                        ? __('Eigen planning houden')
                        : __('Gezien'))
                    ->icon('heroicon-m-check')
                    ->color('gray')
                    ->authorize(fn (FixtureMatch $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (FixtureMatch $record): bool => in_array($record->nevobo_change_status, NevoboChangeStatus::open(), true))
                    ->action(function (FixtureMatch $record): void {
                        app(PlannerService::class)->ignoreNevoboChange($record, auth()->id());
                        Notification::make()->title(__('Verwerkt'))->success()->send();
                    }),
            ])
            ->defaultSort('date');
    }

    private static function matchUrl(FixtureMatch $match): ?string
    {
        if ($match->match_type === MatchType::Home) {
            return class_exists(HomeMatches::class) && HomeMatches::canAccess()
                ? HomeMatches::getUrl(['datum' => $match->date?->toDateString()])
                : null;
        }

        return class_exists(AwayMatches::class) && AwayMatches::canAccess() ? AwayMatches::getUrl() : null;
    }

    /** Shows per field what Nevobo changed, instead of only the new state. */
    private function nevoboSummary(FixtureMatch $match): string
    {
        $changes = collect([
            __('Datum') => [
                $match->nevobo_previous_date?->format('d-m-Y'),
                $match->nevobo_new_date?->format('d-m-Y'),
            ],
            __('Tijd') => [
                FixtureMatch::shortTime($match->nevobo_previous_time),
                FixtureMatch::shortTime($match->nevobo_new_time),
            ],
            __('Sporthal') => [
                $match->nevobo_previous_venue,
                $match->nevobo_new_venue,
            ],
        ])->filter(fn (array $values): bool => filled($values[1]) && $values[0] !== $values[1])
            ->map(fn (array $values, string $field): string => "{$field}: ".($values[0] ?: __('onbekend')).' -> '.$values[1]);

        if ($changes->isNotEmpty()) {
            return __('Nevobo wijzigde :changes', ['changes' => str($changes->implode(' · '))->lcfirst()]);
        }

        // Older notices from before the audit fields stay understandable.
        $new = collect([
            $match->nevobo_new_date?->format('d-m-Y'),
            FixtureMatch::shortTime($match->nevobo_new_time),
            $match->nevobo_new_venue,
        ])->filter()->implode(' · ');

        return $new ? __('Nieuwe Nevobo-planning: :new', ['new' => $new]) : __('Nevobo heeft de wedstrijd gewijzigd.');
    }

    /** @return Builder<FixtureMatch> */
    private function matches(): Builder
    {
        $versionId = $this->versionId();

        return FixtureMatch::query()
            ->when($versionId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('version_id', $versionId)
            ->where('match_type', MatchType::Home)
            ->where(fn (Builder $query) => $query
                ->whereIn('change_status', [ChangeStatus::Requested, ChangeStatus::ToChange])
                ->orWhere('change_requested', true)
                ->orWhereIn('nevobo_change_status', NevoboChangeStatus::open()));
    }
}
