<?php

namespace App\Planner\Filament\Resources\Teams;

use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Teams\Pages\CreateTeam;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\Pages\ListTeams;
use App\Planner\Filament\Resources\Teams\Schemas\TeamForm;
use App\Planner\Filament\Resources\Teams\Tables\TeamsTable;
use App\Planner\Models\Team;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class TeamResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = Team::class;

    protected static ?string $slug = 'teams';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 21;

    public static function getNavigationLabel(): string
    {
        return __('Teams');
    }

    public static function getModelLabel(): string
    {
        return __('Team');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Teams');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return TeamForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return TeamsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeams::route('/'),
            'create' => CreateTeam::route('/create'),
            'edit' => EditTeam::route('/{record}/edit'),
        ];
    }
}
