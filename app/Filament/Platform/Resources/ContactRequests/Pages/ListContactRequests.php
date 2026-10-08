<?php

namespace App\Filament\Platform\Resources\ContactRequests\Pages;

use App\Filament\Platform\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;
}
