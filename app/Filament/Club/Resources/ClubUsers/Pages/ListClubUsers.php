<?php

namespace App\Filament\Club\Resources\ClubUsers\Pages;

use App\Filament\Club\Resources\ClubUsers\ClubUserResource;
use App\Models\Club;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClubUsers extends ListRecords
{
    protected static string $resource = ClubUserResource::class;

    /** Withdraws an open invitation; the link stops working. */
    public function revokeInvitation(int $invitationId): void
    {
        abort_unless(ClubUserResource::canAccess(), 403);
        $club = Filament::getTenant();
        assert($club instanceof Club);

        $club->invitations()->pending()->whereKey($invitationId)->delete();

        Notification::make()->title('Uitnodiging ingetrokken')->success()->send();
    }
}
