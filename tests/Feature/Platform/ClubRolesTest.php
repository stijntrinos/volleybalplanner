<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClubRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_roles_is_idempotent_with_a_committee_role_per_club(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();

        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->artisan('planner:sync-roles')->assertSuccessful();

        // One global club admin role plus a committee role for each club.
        $this->assertSame(3, Role::count());
        $this->assertNotNull(Role::query()->where('name', 'club_admin')->whereNull('club_id')->first());
        $this->assertSame([$a->id, $b->id], Role::query()->where('name', 'planning_committee')->orderBy('club_id')->pluck('club_id')->all());
    }

    public function test_a_role_in_one_club_does_not_leak_to_another(): void
    {
        $this->artisan('planner:sync-roles');
        [$a, $b] = Club::factory()->count(2)->create();
        $user = User::factory()->create();

        $user->assignClubRole($a, ClubRole::ClubAdmin);
        $user->assignClubRole($b, ClubRole::PlanningCommittee);

        $this->assertTrue($user->hasClubRole($a, ClubRole::ClubAdmin));
        $this->assertFalse($user->hasClubRole($b, ClubRole::ClubAdmin));
        $this->assertSame(ClubRole::PlanningCommittee, $user->clubRole($b));
    }
}
