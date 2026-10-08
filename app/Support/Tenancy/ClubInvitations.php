<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invites people to a club. Nobody is added before they accept: a new
 * person creates an account on the invitation page, an existing user logs
 * in and confirms. A member who is already in the club just gets the role.
 */
class ClubInvitations
{
    /** @return Invitation|null null when the person was already a member and only got the role */
    public function invite(Club $club, string $name, string $email, ClubRole $role, ?User $inviter = null): ?Invitation
    {
        $email = Str::lower(trim($email));

        $member = $club->users()->where('email', $email)->first();
        if ($member instanceof User) {
            $member->assignClubRole($club, $role);

            return null;
        }

        $token = Str::random(48);
        $invitation = DB::transaction(function () use ($club, $name, $email, $role, $inviter, $token): Invitation {
            // A new invitation replaces an open one for the same address.
            Invitation::query()->where('club_id', $club->id)->where('email', $email)->whereNull('accepted_at')->delete();

            return Invitation::create([
                'club_id' => $club->id,
                'email' => $email,
                'name' => trim($name),
                'role' => $role,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $inviter?->id,
                'expires_at' => now()->addDays(Invitation::VALID_DAYS),
            ]);
        });

        Notification::route('mail', $email)->notify(new ClubInvitation(
            $club,
            route('invitations.show', $token),
            $inviter?->name,
            $invitation->expires_at,
        ));

        return $invitation;
    }

    /** Adds the user to the club with the invited role and closes the invitation. */
    public function accept(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $user->clubs()->syncWithoutDetaching([$invitation->club_id]);
            $user->assignClubRole($invitation->club, $invitation->role);
            $invitation->update(['accepted_at' => now()]);
        });
    }
}
