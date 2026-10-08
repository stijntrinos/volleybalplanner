<?php

namespace App\Filament\Platform\Resources\Plans\Pages;

use App\Filament\Platform\Resources\Plans\PlanResource;
use App\Models\Plan;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A plan in use stays: its clubs would silently lose their modules.
            DeleteAction::make()->hidden(fn (Plan $record): bool => $record->clubs()->exists()),
        ];
    }
}
