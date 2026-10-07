<?php

namespace App\Planner\Filament\Resources\Availabilities;

use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Availabilities\Pages\CreateAvailability;
use App\Planner\Filament\Resources\Availabilities\Pages\EditAvailability;
use App\Planner\Filament\Resources\Availabilities\Pages\ListAvailabilities;
use App\Planner\Filament\Resources\Availabilities\Schemas\AvailabilityForm;
use App\Planner\Filament\Resources\Availabilities\Tables\AvailabilitiesTable;
use App\Planner\Models\Availability;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class AvailabilityResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = Availability::class;

    protected static ?string $slug = 'availabilities';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 23;

    public static function getNavigationLabel(): string
    {
        return __('Beschikbaarheid');
    }

    public static function getModelLabel(): string
    {
        return __('Beschikbaarheidsregel');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Beschikbaarheidsregels');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AvailabilityForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AvailabilitiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvailabilities::route('/'),
            'create' => CreateAvailability::route('/create'),
            'edit' => EditAvailability::route('/{record}/edit'),
        ];
    }
}
