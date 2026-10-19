<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The set-up page has no club in its URL, so nothing on it may need one
 * (the "Mijn account" link in the user menu did, and crashed the page).
 */
class MfaSetUpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_admin_without_two_factor_can_open_the_set_up_page(): void
    {
        config(['auth.require_platform_admin_mfa' => true]);
        $club = Club::factory()->create();
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->get("/app/{$club->slug}")->assertRedirect();
        $this->actingAs($admin)->get('/app/multi-factor-authentication/set-up')->assertOk();
        $this->actingAs($admin)->get('/admin/multi-factor-authentication/set-up')->assertOk();
    }

    public function test_the_account_link_works_without_a_club_in_the_url(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-a']);
        $user = User::factory()->create();
        $user->clubs()->attach($club);
        $this->actingAs($user);
        Filament::setCurrentPanel('club');

        $item = Filament::getCurrentOrDefaultPanel()->getUserMenuItems()['profile'];

        $this->assertStringEndsWith('/app/vv-a/account', $item->getUrl());
    }
}
