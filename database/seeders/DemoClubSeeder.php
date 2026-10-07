<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use App\Planner\Features\PlannerFeature;
use App\Planner\Support\ClubRole;
use Illuminate\Database\Seeder;

/**
 * Local development only: a club with every module and a club admin
 * (demo@example.com / password).
 */
class DemoClubSeeder extends Seeder
{
    public function run(): void
    {
        $club = Club::query()->updateOrCreate(
            ['slug' => 'demo'],
            ['name' => 'Demo VV', 'is_active' => true, 'features' => PlannerFeature::allValues()],
        );

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Beheerder', 'password' => 'password'],
        );

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->clubs()->syncWithoutDetaching([$club->getKey()]);
        $user->assignClubRole($club, ClubRole::ClubAdmin);
    }
}
