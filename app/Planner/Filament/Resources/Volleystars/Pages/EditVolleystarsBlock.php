<?php

namespace App\Planner\Filament\Resources\Volleystars\Pages;

use App\Planner\Filament\Resources\Volleystars\VolleystarsBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVolleystarsBlock extends EditRecord
{
    protected static string $resource = VolleystarsBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
