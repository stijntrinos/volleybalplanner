<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ClubInvitations
{
    /**
     * Adds a person to a club with a role. Unknown e-mail addresses get an
     * account and a set-password link; existing users keep their password and
     * just get a link to the club.
     */
    public function invite(Club $club, string $name, string $email, ClubRole $role): User
    {
        $email = Str::lower(trim($email));

        [$user, $isNew] = DB::transaction(function () use ($club, $name, $email, $role): array {
            $user = User::query()->where('email', $email)->first();
            $isNew = $user === null;

            $user ??= User::create([
                'name' => trim($name),
                'email' => $email,
                'password' => Str::random(40),
            ]);

            $user->clubs()->syncWithoutDetaching([$club->getKey()]);
            $user->assignClubRole($club, $role);

            return [$user, $isNew];
        });

        $url = $isNew
            ? Filament::getPanel('club')->getResetPasswordUrl(Password::broker()->createToken($user), $user)
            : url("/app/{$club->slug}");

        $user->notify(new ClubInvitation($club, $url, $isNew));

        return $user;
    }
}
