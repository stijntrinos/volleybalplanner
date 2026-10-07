<?php

namespace App\Planner\Filament\Resources\Activities;

use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\Activities\Pages\CreateActivity;
use App\Planner\Filament\Resources\Activities\Pages\EditActivity;
use App\Planner\Filament\Resources\Activities\Pages\ListActivities;
use App\Planner\Filament\Resources\Shared\VersionDefaults;
use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Support\DateFormat;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ActivityResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activities';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::MASTER_DATA;

    protected static ?int $navigationSort = 25;

    public static function getNavigationLabel(): string
    {
        return __('Zaalactiviteiten');
    }

    public static function getModelLabel(): string
    {
        return __('Zaalactiviteit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Zaalactiviteiten');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(12)->components([
            Section::make(__('Activiteit en planning'))
                ->description(__('Geef het blok een herkenbare naam en kies in welke planningversie het thuishoort.'))
                ->columns(12)
                ->schema([
                    TextInput::make('title')
                        ->label(__('Naam activiteit'))
                        ->placeholder(__('Bijv. Jeugdtoernooi of zaalopbouw'))
                        ->required()->maxLength(255)
                        ->columnSpan(['default' => 12, 'md' => 8]),
                    Select::make('version_id')
                        ->label(__('Planningversie'))
                        ->relationship('version', 'name', fn ($query) => VersionDefaults::onlyDrafts($query))
                        ->default(fn (): ?int => VersionDefaults::latestDraftId())
                        ->required()->searchable()->preload()->native(false)
                        ->helperText(__('Zaalactiviteiten worden per planningversie opgeslagen.'))
                        ->columnSpan(['default' => 12, 'md' => 4]),
                ])->columnSpanFull(),
            Section::make(__('Moment en locatie'))
                ->description(__('Bepaal wanneer en waar de activiteit ruimte inneemt bij Thuiswedstrijden.'))
                ->columns(12)
                ->schema([
                    DatePicker::make('date')
                        ->label(__('Datum'))->native(false)->displayFormat('d-m-Y')->required()
                        ->columnSpan(['default' => 12, 'md' => 4]),
                    TimePicker::make('start_time')
                        ->label(__('Begintijd'))->native(false)->displayFormat('H:i')->seconds(false)->required()
                        ->columnSpan(['default' => 6, 'md' => 4]),
                    TimePicker::make('end_time')
                        ->label(__('Eindtijd'))->native(false)->displayFormat('H:i')->seconds(false)->after('start_time')
                        ->helperText(__('Laat leeg wanneer alleen het startmoment bekend is.'))
                        ->columnSpan(['default' => 6, 'md' => 4]),
                    Select::make('venue_id')
                        ->label(__('Sporthal'))
                        ->relationship('venue', 'name', fn ($query) => $query->where('is_active', true)->where('is_managed', true))
                        ->searchable()->preload()->native(false)->live()
                        ->afterStateUpdated(fn (callable $set) => $set('court_id', null))
                        ->columnSpan(['default' => 12, 'md' => 7]),
                    Select::make('court_id')
                        ->label(__('Veld'))
                        ->options(fn (callable $get) => $get('venue_id')
                            ? Court::query()->where('venue_id', $get('venue_id'))->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')
                            : [])
                        ->placeholder(__('Alle velden'))
                        ->helperText(__('Laat leeg als de activiteit de hele sporthal gebruikt.'))
                        ->searchable()->native(false)
                        ->columnSpan(['default' => 12, 'md' => 5]),
                ])->columnSpanFull(),
            Section::make(__('Toelichting'))
                ->description(__('Optionele informatie die planners bij deze activiteit moeten weten.'))
                ->schema([
                    Textarea::make('notes')
                        ->label(__('Interne opmerking'))
                        ->placeholder(__('Bijv. opbouw vanaf 09:00 of alleen halve zaal beschikbaar'))
                        ->rows(3)->maxLength(1000),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('date')
                ->label(__('Datum'))->date(DateFormat::SHORT_WITH_WEEKDAY)->sortable()
                ->description(fn (Activity $record): string => self::timeLabel($record))
                ->icon('heroicon-o-calendar-days'),
            TextColumn::make('title')
                ->label(__('Activiteit'))->searchable()->weight('semibold')
                ->description(fn (Activity $record): ?string => $record->notes ? (string) str($record->notes)->limit(60) : null),
            TextColumn::make('venue.name')
                ->label(__('Locatie'))->searchable()->sortable()->placeholder(__('Geen sporthal'))
                ->description(fn (Activity $record): string => $record->court?->name
                    ? __('Veld :court', ['court' => $record->court->name])
                    : __('Alle velden')),
            TextColumn::make('version.name')
                ->label(__('Planningversie'))
                ->badge()
                ->color(fn (Activity $record): string => VersionDefaults::statusColor($record->version->status))
                ->description(fn (Activity $record): string => VersionDefaults::statusDescription($record->version->status)),
        ])->filters([
            SelectFilter::make('version_id')
                ->label(__('Planningversie'))
                ->relationship('version', 'name')
                ->default(fn (): ?int => VersionDefaults::filterDefault())
                ->searchable()->preload(),
            SelectFilter::make('venue')->label(__('Sporthal'))->relationship('venue', 'name')->searchable()->preload(),
        ])
            ->recordActions([EditAction::make()->label(__('Aanpassen'))])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->defaultSort('date')
            ->emptyStateHeading(__('Nog geen zaalactiviteiten'))
            ->emptyStateDescription(__('Voeg een activiteit toe die ruimte inneemt in een concrete planningversie.'))
            ->emptyStateIcon('heroicon-o-calendar');
    }

    private static function timeLabel(Activity $activity): string
    {
        $start = substr((string) $activity->start_time, 0, 5);
        $end = $activity->end_time ? ' – '.substr($activity->end_time, 0, 5) : '';

        return $start.$end;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }
}
