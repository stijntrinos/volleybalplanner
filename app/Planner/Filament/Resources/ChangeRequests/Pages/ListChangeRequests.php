<?php

namespace App\Planner\Filament\Resources\ChangeRequests\Pages;

use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListChangeRequests extends ListRecords
{
    protected static string $resource = ChangeRequestResource::class;
}
