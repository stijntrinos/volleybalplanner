<?php

namespace App\Planner\Filament\Resources\Trainings\Pages;

use App\Planner\Filament\Resources\Trainings\TrainingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTraining extends EditRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
