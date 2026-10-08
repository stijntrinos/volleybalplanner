<?php

namespace App\Planner\Filament\Resources\Venues;

use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Venues\Pages\CreateVenue;
use App\Planner\Filament\Resources\Venues\Pages\EditVenue;
use App\Planner\Filament\Resources\Venues\Pages\ListVenues;
use App\Planner\Filament\Resources\Venues\RelationManagers\AliasesRelationManager;
use App\Planner\Filament\Resources\Venues\RelationManagers\CourtsRelationManager;
use App\Planner\Filament\Resources\Venues\Schemas\VenueForm;
use App\Planner\Filament\Resources\Venues\Tables\VenuesTable;
use App\Planner\Models\Venue;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class VenueResource extends Resource
{
    use HasPlannerNavigation;

    /** Past the plan limit the create button becomes the upgrade prompt. */
    public static function canCreate(): bool
    {
        return !PlanLimits::reached(PlanLimit::Venues) && parent::canCreate();
    }

    protected static ?string $model = Venue::class;

    protected static ?string $slug = 'venues';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 22;

    public static function getNavigationLabel(): string
    {
        return __('Sporthallen');
    }

    public static function getModelLabel(): string
    {
        return __('Sporthal');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Sporthallen');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return VenueForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return VenuesTable::configure($table);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            CourtsRelationManager::class,
            AliasesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVenues::route('/'),
            'create' => CreateVenue::route('/create'),
            'edit' => EditVenue::route('/{record}/edit'),
        ];
    }
}
