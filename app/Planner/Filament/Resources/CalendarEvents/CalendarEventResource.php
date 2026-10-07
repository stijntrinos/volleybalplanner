<?php

namespace App\Planner\Filament\Resources\CalendarEvents;

use App\Planner\Filament\Concerns\HasPlannerNavigation;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Planner\Filament\Resources\CalendarEvents\Pages\EditCalendarEvent;
use App\Planner\Filament\Resources\CalendarEvents\Pages\ListCalendarEvents;
use App\Planner\Filament\Resources\CalendarEvents\Schemas\CalendarEventForm;
use App\Planner\Filament\Resources\CalendarEvents\Tables\CalendarEventsTable;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\CalendarEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;
use UnitEnum;

class CalendarEventResource extends Resource
{
    use HasPlannerNavigation;

    protected static ?string $model = CalendarEvent::class;

    protected static ?string $slug = 'calendar-events';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::INPUT;

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return __('Bondskalender');
    }

    public static function getModelLabel(): string
    {
        return __('Kalenderactiviteit');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kalenderactiviteiten');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return CalendarEventForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return CalendarEventsTable::configure($table);
    }

    /** Adds the colour of the event type (type_color) for the badge. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->addSelect([
            'planner_calendar_events.*',
            'type_color' => ActivityColor::query()
                ->withoutGlobalScopes()
                ->select('color')
                ->whereColumn('planner_activity_colors.club_id', 'planner_calendar_events.club_id')
                ->whereColumn('planner_activity_colors.type', 'planner_calendar_events.type')
                ->limit(1),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendarEvents::route('/'),
            'create' => CreateCalendarEvent::route('/create'),
            'edit' => EditCalendarEvent::route('/{record}/edit'),
        ];
    }
}
