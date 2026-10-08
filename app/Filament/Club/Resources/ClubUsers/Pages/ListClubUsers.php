<?php

namespace App\Filament\Club\Resources\ClubUsers\Pages;

use App\Filament\Club\Resources\ClubUsers\ClubUserResource;
use App\Models\Club;
use App\Models\User;
use App\Support\Tenancy\ClubInvitations;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListClubUsers extends ListRecords
{
    protected static string $resource = ClubUserResource::class;

    /** Sends a new link (valid for another week); the old link stops working. */
    public function resendInvitation(int $invitationId): void
    {
        abort_unless(ClubUserResource::canAccess(), 403);
        $club = Filament::getTenant();
        assert($club instanceof Club);

        $invitation = $club->invitations()->pending()->findOrFail($invitationId);
        $user = auth()->user();
        app(ClubInvitations::class)->invite($club, $invitation->name, $invitation->email, $invitation->role, $user instanceof User ? $user : null);

        Notification::make()->title('Uitnodiging opnieuw verstuurd')->success()->send();
    }

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
