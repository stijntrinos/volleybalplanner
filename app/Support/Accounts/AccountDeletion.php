<?php

namespace App\Support\Accounts;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * A user deleting their own account (AVG: right to erasure). The club's
 * planning stays; references to the user become empty ("created by",
 * "handled by" are nullOnDelete) and the activity log loses its causer.
 */
class AccountDeletion
{
    /**
     * Why the account cannot be deleted yet; empty when it can.
     *
     * @return list<string>
     */
    public function blockers(User $user): array
    {
        if ($user->isPlatformAdmin()) {
            return [__('Je bent platformbeheerder. Laat een andere platformbeheerder je account verwijderen.')];
        }

        return $user->clubs()->get()
            ->filter(fn (Club $club): bool => $user->hasClubRole($club, ClubRole::ClubAdmin)
                && !$club->users()->whereKeyNot($user->getKey())->get()
                    ->contains(fn (User $other): bool => $other->hasClubRole($club, ClubRole::ClubAdmin)))
            ->map(fn (Club $club): string => __('Je bent de enige clubbeheerder van :club. Maak eerst iemand anders clubbeheerder.', ['club' => $club->name]))
            ->values()
            ->all();
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            Activity::query()->causedBy($user)->update(['causer_type' => null, 'causer_id' => null]);
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            $user->clubs()->detach();
            $user->delete();
        });
    }
}
