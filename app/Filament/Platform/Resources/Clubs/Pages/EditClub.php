<?php

namespace App\Filament\Platform\Resources\Clubs\Pages;

use App\Filament\Platform\Resources\Clubs\ClubResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClub extends EditRecord
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClubResource::openClubAction(),
            DeleteAction::make(),
        ];
    }
}
