<?php

namespace App\Planner\Filament\Resources\Trainings;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Trainings\Pages\CreateTraining;
use App\Planner\Filament\Resources\Trainings\Pages\EditTraining;
use App\Planner\Filament\Resources\Trainings\Pages\ListTrainings;
use App\Planner\Filament\Resources\Trainings\Schemas\TrainingForm;
use App\Planner\Filament\Resources\Trainings\Tables\TrainingsTable;
use App\Planner\Models\Training;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;
use UnitEnum;

class TrainingResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = Training::class;

    protected static ?string $slug = 'trainings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 24;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::Trainings];
    }

    public static function getNavigationLabel(): string
    {
        return __('Trainingsschema');
    }

    public static function getModelLabel(): string
    {
        return __('Training');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Trainingen');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return TrainingForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return TrainingsTable::configure($table);
    }

    /** The weekday select: stored as 'maandag'..'zondag', shown capitalised. @return array<string, string> */
    public static function weekdayOptions(): array
    {
        return array_combine(Training::WEEKDAYS, array_map(fn (string $day): string => __(ucfirst($day)), Training::WEEKDAYS));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainings::route('/'),
            'create' => CreateTraining::route('/create'),
            'edit' => EditTraining::route('/{record}/edit'),
        ];
    }
}
