<?php

namespace App\Planner\Filament\Resources\Volleystars;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Shared\VersionDefaults;
use App\Planner\Filament\Resources\Volleystars\Pages\CreateVolleystarsBlock;
use App\Planner\Filament\Resources\Volleystars\Pages\EditVolleystarsBlock;
use App\Planner\Filament\Resources\Volleystars\Pages\ListVolleystarsBlocks;
use App\Planner\Filament\Resources\Volleystars\RelationManagers\TasksRelationManager;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Support\DateFormat;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VolleystarsBlockResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = VolleystarsBlock::class;

    protected static ?string $slug = 'volleystars';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 6;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::Volleystars];
    }

    public static function getNavigationLabel(): string
    {
        return __('VolleyStars');
    }

    public static function getModelLabel(): string
    {
        return __('VolleyStars-blok');
    }

    public static function getPluralModelLabel(): string
    {
        return __('VolleyStars-blokken');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Planning en locatie'))
                ->description(__('Kies waar en binnen welke planningversie het VolleyStars-programma plaatsvindt.'))
                ->columns(12)
                ->schema([
                    Select::make('version_id')->label(__('Planningversie'))
                        ->relationship('version', 'name', fn ($query) => VersionDefaults::onlyDrafts($query))
                        ->default(fn (): ?int => VersionDefaults::latestDraftId())
                        ->required()->searchable()->preload()->native(false)
                        ->columnSpan(['default' => 12, 'md' => 5]),
                    DatePicker::make('date')->label(__('Datum'))->native(false)->displayFormat('d-m-Y')->required()
                        ->columnSpan(['default' => 12, 'md' => 3]),
                    Select::make('venue_id')->label(__('Sporthal'))
                        ->relationship('venue', 'name', fn ($query) => $query->where('is_active', true)->where('is_managed', true))
                        ->searchable()->preload()->native(false)->required()
                        ->columnSpan(['default' => 12, 'md' => 4]),
                ])->columnSpanFull(),
            Section::make(__('Draaiboek'))
                ->description(__('Leg per fase het tijdsblok vast. Taken en teams voeg je na het opslaan onder dit blok toe.'))
                ->columns(12)
                ->schema([
                    self::timeField('setup_start', __('Opbouw vanaf'), '08:00'),
                    self::endTimeField('setup_end', __('Opbouw tot'), '12:00', 'setup_start', __('opbouwen')),
                    self::timeField('scoring_start', __('Tellen vanaf'), '08:45'),
                    self::endTimeField('scoring_end', __('Tellen tot'), '12:30', 'scoring_start', __('tellen')),
                    self::timeField('refereeing_start', __('Fluiten vanaf'), '08:45'),
                    self::endTimeField('refereeing_end', __('Fluiten tot'), '12:30', 'refereeing_start', __('fluiten')),
                    self::timeField('teardown_start', __('Afbouw vanaf'), '08:30'),
                    self::endTimeField('teardown_end', __('Afbouw tot'), '12:45', 'teardown_start', __('afbouwen')),
                ])->columnSpanFull(),
            Section::make(__('Toelichting'))->schema([
                Textarea::make('notes')->label(__('Interne opmerking'))->rows(3)->maxLength(1000),
            ])->columnSpanFull(),
        ]);
    }

    private static function timeField(string $name, string $label, string $default): TimePicker
    {
        return TimePicker::make($name)
            ->label($label)
            ->native()
            ->seconds(false)
            ->minutesStep(15)
            ->default($default)
            ->required()
            ->columnSpan(['default' => 6, 'md' => 2]);
    }

    private static function endTimeField(string $name, string $label, string $default, string $startField, string $phase): TimePicker
    {
        return self::timeField($name, $label, $default)
            ->rule(fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get, $startField, $phase): void {
                $start = $get($startField);

                if (filled($start) && filled($value) && substr((string) $value, 0, 5) <= substr((string) $start, 0, 5)) {
                    $fail(__('De eindtijd van :phase moet na de begintijd liggen.', ['phase' => $phase]));
                }
            });
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')->label(__('Datum'))->date(DateFormat::SHORT_WITH_WEEKDAY)->sortable()->icon('heroicon-o-calendar-days'),
            TextColumn::make('venue.name')->label(__('Sporthal'))->searchable()->sortable()->weight('semibold'),
            TextColumn::make('schedule')->label(__('Draaiboek'))
                ->state(fn (VolleystarsBlock $record): string => substr((string) $record->setup_start, 0, 5).' – '.substr((string) $record->teardown_end, 0, 5))
                ->description(fn (VolleystarsBlock $record): string => __('Tellen :start – :end', [
                    'start' => substr((string) $record->scoring_start, 0, 5),
                    'end' => substr((string) $record->scoring_end, 0, 5),
                ]))
                ->icon('heroicon-o-clock'),
            TextColumn::make('tasks_count')->counts('tasks')->label(__('Taken'))
                ->badge()->color(fn (VolleystarsBlock $record): string => $record->tasks_count > 0 ? 'success' : 'warning')
                ->formatStateUsing(fn (int $state): string => $state === 1 ? __('1 taak') : __(':count taken', ['count' => $state]))
                ->description(fn (VolleystarsBlock $record): string => __(':count toegewezen', ['count' => $record->assigned_tasks_count ?? 0])),
            TextColumn::make('version.name')->label(__('Planningversie'))->badge()
                ->color(fn (VolleystarsBlock $record): string => VersionDefaults::statusColor($record->version->status)),
        ])
            ->modifyQueryUsing(fn ($query) => $query->withCount(['tasks as assigned_tasks_count' => fn ($query) => $query->whereNotNull('team_id')]))
            ->filters([
                SelectFilter::make('version_id')->label(__('Planningversie'))->relationship('version', 'name')
                    ->default(fn (): ?int => VersionDefaults::filterDefault())->searchable()->preload(),
                SelectFilter::make('venue')->label(__('Sporthal'))->relationship('venue', 'name')->searchable()->preload(),
            ])
            ->recordActions([EditAction::make()->label(__('Taken beheren'))])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])->defaultSort('date')
            ->emptyStateHeading(__('Nog geen VolleyStars-blokken'))
            ->emptyStateDescription(__('Voeg een programma toe en verdeel daarna de opbouw-, tel- en afbouwtaken.'))
            ->emptyStateIcon('heroicon-o-sparkles');
    }

    public static function getRelations(): array
    {
        return [TasksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVolleystarsBlocks::route('/'),
            'create' => CreateVolleystarsBlock::route('/create'),
            'edit' => EditVolleystarsBlock::route('/{record}/edit'),
        ];
    }
}
