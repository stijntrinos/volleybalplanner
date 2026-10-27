<?php

namespace App\Planner\Filament\Resources\HallDuties;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\HallDuties\Pages\CreateHallDuty;
use App\Planner\Filament\Resources\HallDuties\Pages\EditHallDuty;
use App\Planner\Filament\Resources\HallDuties\Pages\ListHallDuties;
use App\Planner\Filament\Resources\Shared\VersionDefaults;
use App\Planner\Models\HallDuty;
use App\Planner\Support\DateFormat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class HallDutyResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = HallDuty::class;

    protected static ?string $slug = 'hall-duties';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 5;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::Duties];
    }

    public static function getNavigationLabel(): string
    {
        return __('Zaaldiensten');
    }

    public static function getModelLabel(): string
    {
        return __('Zaaldienst');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Zaaldiensten');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Planning en locatie'))
                ->description(__('Kies in welke planningversie en sporthal deze zaaldienst geldt.'))
                ->columns(12)
                ->schema([
                    Select::make('version_id')
                        ->label(__('Planningversie'))
                        ->relationship('version', 'name', fn ($query) => VersionDefaults::onlyDrafts($query))
                        ->default(fn (): ?int => VersionDefaults::latestDraftId())
                        ->required()->searchable()->preload()->native(false)
                        ->columnSpan(['default' => 12, 'md' => 5]),
                    Select::make('venue_id')
                        ->label(__('Sporthal'))
                        ->relationship('venue', 'name', fn ($query) => $query->where('is_active', true)->where('is_managed', true))
                        ->searchable()->preload()->native(false)->required()
                        ->columnSpan(['default' => 12, 'md' => 7]),
                ])->columnSpanFull(),
            Section::make(__('Moment en verantwoordelijk team'))
                ->description(__('Leg het volledige tijdsblok vast waarin het team verantwoordelijk is voor de zaal.'))
                ->columns(12)
                ->schema([
                    DatePicker::make('date')->label(__('Datum'))->native(false)->displayFormat('d-m-Y')->required()
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    TimePicker::make('start_time')->label(__('Starttijd'))->native(false)->displayFormat('H:i')->seconds(false)->required()
                        ->columnSpan(['default' => 6, 'md' => 4]),
                    TimePicker::make('end_time')->label(__('Eindtijd'))->native(false)->displayFormat('H:i')->seconds(false)->after('start_time')->required()
                        ->columnSpan(['default' => 6, 'md' => 4]),
                    Select::make('team_id')
                        ->label(__('Verantwoordelijk team'))
                        ->relationship('team', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} · {$record->name}")
                        ->searchable(['code', 'name'])->preload()->native(false)->required()
                        ->columnSpanFull(),
                ])->columnSpanFull(),
            Section::make(__('Toelichting'))
                ->schema([
                    Textarea::make('notes')->label(__('Interne opmerking'))->placeholder(__('Bijv. sleutel ophalen bij beheerder'))->rows(3)->maxLength(1000),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')
                ->label(__('Datum'))->date(DateFormat::SHORT_WITH_WEEKDAY)->sortable()->icon('heroicon-o-calendar-days')
                ->description(fn (HallDuty $record): string => substr((string) $record->start_time, 0, 5).' – '.substr((string) $record->end_time, 0, 5)),
            TextColumn::make('team.code')
                ->label(__('Verantwoordelijk team'))->searchable()->weight('semibold')
                ->description(fn (HallDuty $record): string => $record->team->name),
            TextColumn::make('venue.name')->label(__('Sporthal'))->searchable()->sortable(),
            TextColumn::make('version.name')
                ->label(__('Planningversie'))->badge()
                ->color(fn (HallDuty $record): string => VersionDefaults::statusColor($record->version->status)),
            TextColumn::make('notes')->label(__('Opmerking'))->limit(45)->tooltip(fn (HallDuty $record): ?string => $record->notes)->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('version_id')
                ->label(__('Planningversie'))->relationship('version', 'name')
                ->default(fn (): ?int => VersionDefaults::filterDefault())->searchable()->preload(),
            SelectFilter::make('venue')->label(__('Sporthal'))->relationship('venue', 'name')->searchable()->preload(),
            SelectFilter::make('team')->label(__('Team'))->relationship('team', 'name')->searchable()->preload(),
        ])
            ->recordActions([EditAction::make()->label(__('Aanpassen'))])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('date')
            ->emptyStateHeading(__('Nog geen zaaldiensten'))
            ->emptyStateDescription(__('Voeg handmatig een zaaldienst toe of genereer deze via de FTZ-planning.'))
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHallDuties::route('/'),
            'create' => CreateHallDuty::route('/create'),
            'edit' => EditHallDuty::route('/{record}/edit'),
        ];
    }
}
