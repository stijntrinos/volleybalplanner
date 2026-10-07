<?php

namespace App\Planner\Filament\Resources\CalendarEvents\Pages;

use App\Planner\Filament\Resources\CalendarEvents\CalendarEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCalendarEvent extends CreateRecord
{
    protected static string $resource = CalendarEventResource::class;
}
