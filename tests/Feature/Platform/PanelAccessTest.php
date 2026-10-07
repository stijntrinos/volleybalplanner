<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_reach_the_platform_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->platformAdmin()->create())->get('/admin')->assertOk();
    }

    public function test_member_reaches_own_club_but_not_another(): void
    {
        [$own, $other] = Club::factory()->count(2)->create();
        $user = User::factory()->create();
        $user->clubs()->attach($own);

        $this->actingAs($user)->get("/app/{$own->slug}")->assertOk();
        $this->actingAs($user)->get("/app/{$other->slug}")->assertNotFound();
    }

    public function test_deactivated_club_is_closed_for_logged_in_members(): void
    {
        $club = Club::factory()->create();
        $user = User::factory()->create();
        $user->clubs()->attach($club);
        $this->actingAs($user)->get("/app/{$club->slug}")->assertOk();

        $club->update(['is_active' => false]);

        $this->actingAs($user)->get("/app/{$club->slug}")->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $club = Club::factory()->create();

        $this->get("/app/{$club->slug}")->assertRedirect('/app/login');
    }

    public function test_platform_admin_reaches_every_club_without_membership(): void
    {
        $active = Club::factory()->create();
        $inactive = Club::factory()->inactive()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get("/app/{$active->slug}")->assertOk();
        $this->actingAs($admin)->get("/app/{$inactive->slug}")->assertOk();
        $this->actingAs($admin)->get("/app/{$active->slug}/club-users")->assertOk();
        $this->assertTrue($admin->can('Update:FixtureMatch'));
    }
}
