<?php

namespace App\Planner\Filament\Resources\Teams\Tables;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Teams\Schemas\TeamForm;
use App\Planner\Models\Team;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        $club = app(CurrentClub::class)->get();
        $volunteerPoints = (bool) $club?->hasFeature(PlannerFeature::VolunteerPoints);
        $lockerRooms = (bool) $club?->hasFeature(PlannerFeature::LockerRooms);

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('Team'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->description(fn (Team $record): string => $record->name ?: __('Geen naam')),

                TextColumn::make('default_planning')
                    ->label(__('Standaardplanning'))
                    ->state(fn (Team $record): string => collect([
                        $record->default_weekday,
                        $record->default_time ? substr($record->default_time, 0, 5) : null,
                    ])->filter()->implode(' · ') ?: __('Niet compleet'))
                    ->description(fn (Team $record): string => collect([
                        $record->defaultVenue?->name,
                        $record->default_court ? __('veld :court', ['court' => $record->default_court]) : null,
                    ])->filter()->implode(' · ') ?: __('Geen standaardlocatie'))
                    ->color(fn (Team $record): string => $record->default_weekday && $record->default_time && $record->default_venue_id ? 'gray' : 'warning'),

                ViewColumn::make('traits')
                    ->label(__('Inzet & automatisering'))
                    ->view('planner::filament.tables.columns.team-traits'),

                TextColumn::make('points_balance')
                    ->label(__('Puntenbalans'))
                    ->visible($volunteerPoints)
                    ->state(fn (Team $record): int => $record->volunteer_points_earned - $record->volunteer_points_used)
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->description(fn (Team $record): string => __(':earned geleverd · :used ontvangen', [
                        'earned' => $record->volunteer_points_earned,
                        'used' => $record->volunteer_points_used,
                    ]))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state > 0 => 'success',
                        $state < 0 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(
                        '(volunteer_points_earned - volunteer_points_used) '.($direction === 'desc' ? 'desc' : 'asc'),
                    )),
            ])
            ->filters(array_values(array_filter([
                $lockerRooms ? SelectFilter::make('locker_room_category')
                    ->label(__('Kleedkamercategorie'))
                    ->options(TeamForm::lockerRoomOptions()) : null,
                SelectFilter::make('default_venue_id')
                    ->label(__('Standaard sporthal'))
                    ->relationship('defaultVenue', 'name')
                    ->searchable()
                    ->preload(),
            ])))
            ->modifyQueryUsing(fn ($query) => $query->with('defaultVenue'))
            ->recordActions([
                EditAction::make()->label(__('Beheren')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code')
            ->emptyStateHeading(__('Nog geen teams'))
            ->emptyStateDescription(__('Voeg teams toe of importeer een wedstrijdplanning om te beginnen.'))
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
