<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Nobody joins a club before accepting: an invitation is a link to a page
 * where a new person creates an account and an existing user confirms.
 */
class ClubInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $url = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('planner:sync-roles');
        Notification::fake();
    }

    public function test_an_invitation_adds_nobody_until_it_is_accepted(): void
    {
        $club = Club::factory()->create();
        $existing = User::factory()->create(['email' => 'piet@example.nl']);

        $invitation = app(ClubInvitations::class)->invite($club, 'Piet', ' Piet@Example.nl ', ClubRole::PlanningCommittee);

        $this->assertSame('piet@example.nl', $invitation->email);
        $this->assertFalse($existing->clubs()->whereKey($club->id)->exists());
        $this->assertTrue($invitation->expires_at->isAfter(now()->addDays(6)));
        Notification::assertSentOnDemand(ClubInvitation::class, fn (ClubInvitation $n, array $channels, AnonymousNotifiable $to): bool => $to->routes['mail'] === 'piet@example.nl');
    }

    public function test_a_new_person_creates_an_account_on_the_invitation_page(): void
    {
        $club = Club::factory()->create();
        $token = $this->invite($club, 'jan@example.nl', ClubRole::ClubAdmin);

        $this->get("/uitnodiging/{$token}")->assertOk()->assertSee('Account maken en accepteren');

        $this->post("/uitnodiging/{$token}/accepteren", ['name' => 'Jan', 'password' => 'Geheim-123!', 'password_confirmation' => 'Geheim-123!'])
            ->assertRedirect(url("/app/{$club->slug}"));

        $user = User::firstWhere('email', 'jan@example.nl');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasClubRole($club, ClubRole::ClubAdmin));
        $this->assertNotNull(Invitation::sole()->accepted_at);
    }

    public function test_an_existing_user_must_log_in_with_the_invited_address_to_accept(): void
    {
        $club = Club::factory()->create();
        $piet = User::factory()->create(['email' => 'piet@example.nl']);
        $token = $this->invite($club, 'piet@example.nl', ClubRole::PlanningCommittee);

        $this->get("/uitnodiging/{$token}")->assertOk()->assertSee('Inloggen');
        $this->post("/uitnodiging/{$token}/accepteren")->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->get("/uitnodiging/{$token}")->assertSee('ander account');
        $this->post("/uitnodiging/{$token}/accepteren")->assertForbidden();

        $this->actingAs($piet);
        $this->get("/uitnodiging/{$token}")->assertSee('Uitnodiging accepteren');
        $this->post("/uitnodiging/{$token}/accepteren")->assertRedirect(url("/app/{$club->slug}"));

        $this->assertTrue($piet->hasClubRole($club, ClubRole::PlanningCommittee));
    }

    public function test_a_used_expired_or_declined_link_stops_working(): void
    {
        $club = Club::factory()->create();
        $token = $this->invite($club, 'jan@example.nl', ClubRole::ClubAdmin);

        $this->post("/uitnodiging/{$token}/afwijzen")->assertRedirect(route('site.home'));
        $this->get("/uitnodiging/{$token}")->assertOk()->assertSee('Deze link werkt niet meer');

        $token = $this->invite($club, 'jan@example.nl', ClubRole::ClubAdmin);
        $this->travel(Invitation::VALID_DAYS + 1)->days();
        $this->post("/uitnodiging/{$token}/accepteren", ['name' => 'Jan', 'password' => 'Geheim-123!', 'password_confirmation' => 'Geheim-123!'])
            ->assertNotFound();
        $this->assertNull(User::firstWhere('email', 'jan@example.nl'));
    }

    public function test_inviting_a_member_again_only_changes_the_role(): void
    {
        $club = Club::factory()->create();
        $user = User::factory()->create(['email' => 'jan@example.nl']);
        $user->clubs()->attach($club);
        $user->assignClubRole($club, ClubRole::ClubAdmin);

        $this->assertNull(app(ClubInvitations::class)->invite($club, 'Jan', 'jan@example.nl', ClubRole::PlanningCommittee));

        $this->assertSame(1, $user->clubs()->count());
        $this->assertTrue($user->hasClubRole($club, ClubRole::PlanningCommittee));
        $this->assertSame(0, Invitation::count());
        Notification::assertNothingSent();
    }

    public function test_open_invitations_count_against_the_admin_limit(): void
    {
        $club = Club::factory()->create();
        $this->invite($club, 'a@example.nl', ClubRole::PlanningCommittee);

        $this->assertSame(1, $club->seatsTaken());
    }

    private function invite(Club $club, string $email, ClubRole $role): string
    {
        app(ClubInvitations::class)->invite($club, 'Jan', $email, $role);
        $token = null;
        Notification::assertSentOnDemand(ClubInvitation::class, function (ClubInvitation $notification) use (&$token): bool {
            $token = basename($notification->url);

            return true;
        });

        return (string) $token;
    }
}
