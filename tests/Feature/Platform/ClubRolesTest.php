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

    public function test_sync_roles_is_idempotent_and_creates_both_roles(): void
    {
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->artisan('planner:sync-roles')->assertSuccessful();

        $this->assertSame(2, Role::count());
        $this->assertNotNull(Role::findByName('club_admin'));
        $this->assertNotNull(Role::findByName('planning_committee'));
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
