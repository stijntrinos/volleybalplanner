<?php

namespace App\Support\Accounts;

use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passkeys\Passkey;
use Spatie\Activitylog\Models\Activity;

/**
 * Everything Volleybalplanner keeps about a user, for the download on "Mijn
 * account" (AVG: right of access and data portability). The planning
 * itself belongs to the club; only who changed what is personal.
 */
class PersonalData
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $memberSince = DB::table('club_user')->where('user_id', $user->getKey())->pluck('created_at', 'club_id');

        return [
            'exported_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
                'platform_admin' => $user->isPlatformAdmin(),
                'two_factor_authentication' => filled($user->getAppAuthenticationSecret()),
            ],
            'passkeys' => $user->passkeys()->get()->map(fn (Passkey $passkey): array => [
                'name' => $passkey->name,
                'created_at' => $passkey->created_at?->toIso8601String(),
                'last_used_at' => $passkey->last_used_at?->toIso8601String(),
            ])->all(),
            'clubs' => $user->clubs()->get()->map(fn (Club $club): array => [
                'club' => $club->name,
                'role' => $user->clubRole($club)?->getLabel(),
                'member_since' => $memberSince[$club->getKey()] ?? null,
            ])->all(),
            'activity' => Activity::query()->causedBy($user)->latest('id')->get()->map(fn (Activity $activity): array => [
                'log' => $activity->log_name,
                'description' => $activity->description,
                'subject' => $activity->subject_type ? class_basename($activity->subject_type).'#'.$activity->subject_id : null,
                'properties' => $activity->properties?->toArray() ?? [],
                'created_at' => $activity->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    public function filename(User $user): string
    {
        return 'volleybalplanner-gegevens-'.now()->format('Y-m-d').'.json';
    }
}
