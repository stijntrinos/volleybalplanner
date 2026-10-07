<?php

namespace App\Filament\Platform\Resources\Clubs\Tables;

use App\Filament\Platform\Resources\Clubs\ClubResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClubsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
                TextColumn::make('users_count')->label('Gebruikers')->counts('users'),
                TextColumn::make('created_at')->label('Aangemaakt')->dateTime('d-m-Y')->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ClubResource::openClubAction(),
                EditAction::make(),
            ]);
    }
}
