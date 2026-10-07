<?php

namespace App\Planner\Filament\Resources\HallDuties\Pages;

use App\Planner\Filament\Resources\HallDuties\HallDutyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHallDuty extends EditRecord
{
    protected static string $resource = HallDutyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
