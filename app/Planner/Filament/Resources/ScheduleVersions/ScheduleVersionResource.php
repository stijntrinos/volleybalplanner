<?php

namespace App\Planner\Filament\Resources\ScheduleVersions;

use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\CreateScheduleVersion;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\EditScheduleVersion;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\ListScheduleVersions;
use App\Planner\Filament\Resources\ScheduleVersions\Schemas\ScheduleVersionForm;
use App\Planner\Filament\Resources\ScheduleVersions\Tables\ScheduleVersionsTable;
use App\Planner\Models\ScheduleVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class ScheduleVersionResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = ScheduleVersion::class;

    protected static ?string $slug = 'schedule-versions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::INPUT;

    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return __('Planningversies');
    }

    public static function getModelLabel(): string
    {
        return __('Planningversie');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Planningversies');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return ScheduleVersionForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return ScheduleVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScheduleVersions::route('/'),
            'create' => CreateScheduleVersion::route('/create'),
            'edit' => EditScheduleVersion::route('/{record}/edit'),
        ];
    }
}
