<?php

namespace App\Filament\Platform\Resources\Clubs;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
use App\Filament\Platform\Resources\Clubs\Pages\EditClub;
use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Filament\Platform\Resources\Clubs\RelationManagers\UsersRelationManager;
use App\Filament\Platform\Resources\Clubs\Schemas\ClubForm;
use App\Filament\Platform\Resources\Clubs\Tables\ClubsTable;
use App\Models\Club;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'club';

    protected static ?string $pluralModelLabel = 'clubs';

    public static function form(Schema $schema): Schema
    {
        return ClubForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClubsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClubs::route('/'),
            'create' => CreateClub::route('/create'),
            'edit' => EditClub::route('/{record}/edit'),
        ];
    }

    /**
     * Opens the club's workspace; platform admins can enter every club.
     */
    public static function openClubAction(): Action
    {
        return Action::make('openClub')
            ->label('Naar club')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (Club $record): string => Filament::getPanel('club')->getUrl($record))
            ->openUrlInNewTab();
    }
}
