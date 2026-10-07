<?php

namespace App\Filament\Club\Resources\ClubUsers\Pages;

use App\Filament\Club\Resources\ClubUsers\ClubUserResource;
use Filament\Resources\Pages\ListRecords;

class ListClubUsers extends ListRecords
{
    protected static string $resource = ClubUserResource::class;
}
