<?php

namespace App\Planner\Filament\Resources\CalendarEvents\Pages;

use App\Planner\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Planner\Models\CalendarEvent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListCalendarEvents extends ListRecords
{
    protected static string $resource = CalendarEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('Kalenderactiviteit toevoegen'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }

    public function getSubheading(): ?string
    {
        return __('Vakanties, feestdagen, bekerrondes en andere periodes die invloed hebben op de wedstrijdplanning.');
    }

    public function getTabs(): array
    {
        return [
            'current' => Tab::make(__('Actueel & aankomend'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->badge(CalendarEvent::query()->whereDate('end_date', '>=', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('end_date', '>=', today())),
            'past' => Tab::make(__('Verlopen'))
                ->icon(Heroicon::OutlinedArchiveBox)
                ->badge(CalendarEvent::query()->whereDate('end_date', '<', today())->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('end_date', '<', today())),
            'all' => Tab::make(__('Alles'))
                ->badge(CalendarEvent::query()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'current';
    }
}
