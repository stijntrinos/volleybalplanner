<?php

namespace App\Planner\Filament\Resources\ScheduleVersions\Pages;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduleVersion extends CreateRecord
{
    protected static string $resource = ScheduleVersionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = ScheduleVersionStatus::Draft;

        return $data;
    }
}
