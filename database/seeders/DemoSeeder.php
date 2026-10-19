<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Plan;
use App\Models\User;
use App\Planner\Support\ClubRole;
use App\Support\Demo\DemoClubBuilder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * The fixed local demo club "VC Voorbeeld", filled by DemoClubBuilder
 * with the data the design system was drawn with (dates relative to
 * today). `php artisan db:seed --class=DemoSeeder`, then sign in as
 * demo@volleybalplanner.test / password.
 */
class DemoSeeder extends Seeder
{
    public const CLUB = DemoClubBuilder::TEAM_PREFIX;

    public const EMAIL = 'demo@volleybalplanner.test';

    public function run(): void
    {
        // A known password and a platform admin: never on a live server.
        if (app()->isProduction()) {
            throw new RuntimeException('De DemoSeeder draait niet in productie.');
        }

        Artisan::call('planner:sync-roles');

        $club = Club::query()->firstOrCreate(
            ['slug' => 'vc-voorbeeld'],
            ['name' => self::CLUB],
        );
        // Vereniging: everything but zitvolleybal, so a locked module shows too.
        $club->update(['plan_id' => Plan::query()->where('slug', 'vereniging')->value('id')]);

        $user = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Stijn', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        // Also a platform admin, so /admin can be tried with the same account.
        $user->forceFill(['is_platform_admin' => true])->save();
        $user->clubs()->syncWithoutDetaching([$club->getKey()]);
        $user->assignClubRole($club, ClubRole::ClubAdmin);

        app(DemoClubBuilder::class)->fill($club);
    }
}
