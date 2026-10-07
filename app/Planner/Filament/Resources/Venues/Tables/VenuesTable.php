<?php

namespace App\Planner\Filament\Resources\Venues\Tables;

use App\Planner\Enums\Source;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        $club = app(CurrentClub::class)->get();
        $duties = (bool) $club?->hasFeature(PlannerFeature::Duties);
        $trainings = (bool) $club?->hasFeature(PlannerFeature::Trainings);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Sporthal'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Venue $record): string => collect([$record->address, $record->city])->filter()->implode(', ') ?: __('Geen adres ingevuld')),

                TextColumn::make('courts_count')
                    ->label(__('Inrichting'))
                    ->counts('courts')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): string => $state === 1 ? __('1 veld') : __(':count velden', ['count' => $state]))
                    ->description(fn (Venue $record): string => ($record->aliases_count ?? 0) === 1
                        ? __('1 importnaam')
                        : __(':count importnamen', ['count' => $record->aliases_count ?? 0])),

                TextColumn::make('usage')
                    ->label(__('Gebruik'))
                    ->state(fn (Venue $record): array => collect([
                        __('Thuiswedstrijden') => $record->is_managed,
                        __('FTZ') => $duties && $record->has_duties,
                        __('Wedstrijden') => $record->for_matches,
                        __('Trainingen') => $trainings && $record->for_trainings,
                    ])->filter()->keys()->all())
                    ->badge()
                    ->color('primary')
                    ->placeholder(__('Niet ingesteld')),

                TextColumn::make('is_active')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? __('Actief') : __('Inactief'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),

                TextColumn::make('venue_code')
                    ->label(__('Nevobo-code'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('source')
                    ->label(__('Bron'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?Source $state): string => $state === Source::MatchImport ? __('Wedstrijdimport') : __('Handmatig'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn ($query) => $query->withCount('aliases'))
            ->filters([
                SelectFilter::make('is_managed')
                    ->label(__('Beheerd'))
                    ->options([
                        1 => __('Beheerd'),
                        0 => __('Niet beheerd'),
                    ]),
                SelectFilter::make('usage')
                    ->label(__('Geschikt voor'))
                    ->options(array_filter([
                        'has_duties' => $duties ? __('FTZ') : null,
                        'for_matches' => __('Wedstrijden'),
                        'for_trainings' => $trainings ? __('Trainingen') : null,
                    ]))
                    ->query(fn ($query, array $data) => in_array($data['value'] ?? null, ['has_duties', 'for_matches', 'for_trainings'], true)
                        ? $query->where($data['value'], true)
                        : $query),
            ])
            ->recordActions([
                EditAction::make()->label(__('Beheren')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading(__('Nog geen sporthallen'))
            ->emptyStateDescription(__('Voeg een sporthal toe en configureer daarna de velden en importnamen.'))
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
