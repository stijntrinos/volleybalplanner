<?php

namespace App\Planner\Filament\Resources\CalendarEvents\Pages;

use App\Planner\Filament\Resources\CalendarEvents\CalendarEventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalendarEvent extends EditRecord
{
    protected static string $resource = CalendarEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
