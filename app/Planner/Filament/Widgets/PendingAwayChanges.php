<?php

namespace App\Planner\Filament\Widgets;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\RequiresPlannerFeature;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Widgets\Concerns\ResolvesWidgetVersion;
use App\Planner\Models\FixtureMatch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The away matches with a running change, with what was agreed with the home
 * club. They are kept apart from the home matches in Pending changes.
 */
class PendingAwayChanges extends TableWidget
{
    use RequiresPlannerFeature;
    use ResolvesWidgetVersion;

    /** An away match ends at none, final or cancelled; only these are still running. */
    public const OPEN_STATUSES = [AwayChangeStatus::InProgress, AwayChangeStatus::InDiscussion];

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::AwayMatches];
    }

    /** The same rights as the home matches list; no extra permission needed. */
    public static function canView(): bool
    {
        return static::plannerFeaturesEnabled() && PendingChanges::canView();
    }

    public function table(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'planner-widget'])
            ->striped()
            ->heading(__('Lopende wijzigingen uitwedstrijden'))
            ->description(__('Uitwedstrijden die nog in overleg zijn of waarvan de wijziging loopt.'))
            ->query($this->matches())
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->emptyStateHeading(__('Geen lopende wijzigingen'))
            ->emptyStateDescription(__('Uitwedstrijden met de status "Loopt" of "In overleg" verschijnen hier.'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (): ?string => class_exists(AwayMatches::class) && AwayMatches::canAccess() ? AwayMatches::getUrl() : null)
            ->columns([
                TextColumn::make('date')
                    ->label(__('Datum'))
                    ->date('d-m-Y')
                    ->placeholder(__('Nog geen datum'))
                    ->sortable(),

                TextColumn::make('time')
                    ->label(__('Tijd'))
                    ->formatStateUsing(fn (?string $state): string => $state ? substr($state, 0, 5) : '-'),

                TextColumn::make('match_code')
                    ->label(__('Code'))
                    ->searchable(),

                TextColumn::make('home_team_name')
                    ->label(__('Wedstrijd'))
                    ->state(fn (FixtureMatch $record): string => trim($record->home_team_name.' - '.$record->away_team_name, ' -'))
                    ->searchable(['home_team_name', 'away_team_name']),

                TextColumn::make('away_change_status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?AwayChangeStatus $state): string => $state === AwayChangeStatus::InDiscussion ? 'warning' : 'info')
                    ->formatStateUsing(fn (?AwayChangeStatus $state): string => $state === AwayChangeStatus::InDiscussion ? __('In overleg') : __('Wijziging loopt'))
                    ->description(fn (FixtureMatch $record): ?string => $this->proposal($record)),

                TextColumn::make('away_notes')
                    ->label(__('Opmerkingen'))
                    ->limit(60)
                    ->placeholder('-'),
            ])
            ->defaultSort('date');
    }

    /** What was agreed as the new date, time or venue, when there is something. */
    private function proposal(FixtureMatch $match): ?string
    {
        $parts = collect([
            $match->away_new_date?->format('d-m-Y'),
            FixtureMatch::shortTime($match->away_new_time),
            $match->away_new_venue,
        ])->filter();

        return $parts->isEmpty() ? null : __('Nieuw: :proposal', ['proposal' => $parts->implode(' · ')]);
    }

    /** @return Builder<FixtureMatch> */
    private function matches(): Builder
    {
        $versionId = $this->versionId();

        return FixtureMatch::query()
            ->when($versionId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('version_id', $versionId)
            ->where('match_type', MatchType::Away)
            ->whereIn('away_change_status', self::OPEN_STATUSES);
    }
}
