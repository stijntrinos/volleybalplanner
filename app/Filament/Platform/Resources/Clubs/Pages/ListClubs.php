<?php

namespace App\Filament\Platform\Resources\Clubs\Pages;

use App\Filament\Platform\Resources\Clubs\ClubResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClubs extends ListRecords
{
    protected static string $resource = ClubResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ClubResource::createDemoAction(),
            CreateAction::make(),
        ];
    }
}
