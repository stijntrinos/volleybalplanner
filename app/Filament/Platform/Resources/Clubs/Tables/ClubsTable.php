<?php

namespace App\Filament\Platform\Resources\Clubs\Tables;

use App\Filament\Platform\Resources\Clubs\ClubResource;
use App\Models\Club;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ClubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('is_demo')
                    ->label(__('Soort'))
                    ->badge()
                    ->state(fn (Club $record): ?string => $record->is_demo ? __('Demo') : null)
                    ->color('info')
                    ->description(fn (Club $record): ?string => $record->is_demo && $record->trial_ends_at
                        ? __('Proef tot :date', ['date' => $record->trial_ends_at->format('d-m-Y')])
                        : null),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('plan.name')->label('Pakket')->badge()->color('gray')->placeholder('Geen pakket')->sortable(),
                TextColumn::make('subscription_ends_at')
                    ->label('Loopt tot')
                    ->date('d-m-Y')
                    ->placeholder('Doorlopend')
                    ->description(fn (Club $record): ?string => $record->subscriptionNotice()['message'] ?? null)
                    ->color(fn (Club $record): ?string => $record->subscriptionNotice()['tone'] ?? null)
                    ->sortable(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
                TextColumn::make('users_count')->label('Gebruikers')->counts('users'),
                TextColumn::make('created_at')->label('Aangemaakt')->dateTime('d-m-Y')->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_demo')->label(__('Demo')),
            ])
            ->recordActions([
                ClubResource::openClubAction(),
                ClubResource::convertDemoAction(),
                ClubResource::linkNevoboAction(),
                EditAction::make(),
            ]);
    }
}
