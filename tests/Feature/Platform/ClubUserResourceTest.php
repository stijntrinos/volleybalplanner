<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubUserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('planner:sync-roles');
    }

    public function test_only_club_admins_see_user_management(): void
    {
        $club = Club::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $club->users()->attach([$admin->id, $member->id]);
        $admin->assignClubRole($club, ClubRole::ClubAdmin);
        $member->assignClubRole($club, ClubRole::PlanningCommittee);

        $this->actingAs($admin)->get("/app/{$club->slug}/club-users")->assertOk()->assertSee($member->email);
        $this->actingAs($member)->get("/app/{$club->slug}/club-users")->assertForbidden();
    }

    public function test_admin_of_one_club_is_not_admin_in_another(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $user = User::factory()->create();
        $user->clubs()->attach([$a->id, $b->id]);
        $user->assignClubRole($a, ClubRole::ClubAdmin);
        $user->assignClubRole($b, ClubRole::PlanningCommittee);

        $this->actingAs($user)->get("/app/{$b->slug}/club-users")->assertForbidden();
    }

    public function test_list_only_contains_members_of_the_current_club(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        $a->users()->attach($admin);
        $b->users()->attach($outsider);
        $admin->assignClubRole($a, ClubRole::ClubAdmin);

        $this->actingAs($admin)->get("/app/{$a->slug}/club-users")->assertOk()->assertDontSee($outsider->email);
    }
}
