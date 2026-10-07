<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClubInvitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('planner:sync-roles');
        Notification::fake();
    }

    public function test_inviting_a_new_email_creates_a_member_with_role(): void
    {
        $club = Club::factory()->create();

        $user = app(ClubInvitations::class)->invite($club, 'Jan', '  Jan@Example.nl ', ClubRole::ClubAdmin);

        $this->assertSame('jan@example.nl', $user->email);
        $this->assertTrue($user->clubs->contains($club));
        $this->assertTrue($user->hasClubRole($club, ClubRole::ClubAdmin));
        Notification::assertSentTo($user, ClubInvitation::class, fn (ClubInvitation $n): bool => $n->isNewUser && str_contains($n->url, 'password-reset'));
    }

    public function test_inviting_an_existing_user_to_a_second_club_keeps_password(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $user = User::factory()->create(['email' => 'piet@example.nl']);
        $hash = $user->password;
        $user->clubs()->attach($a);

        $same = app(ClubInvitations::class)->invite($b, 'Piet', 'piet@example.nl', ClubRole::PlanningCommittee);

        $this->assertTrue($same->is($user));
        $this->assertSame($hash, $same->fresh()->password);
        $this->assertSame(2, $same->clubs()->count());
        Notification::assertSentTo($user, ClubInvitation::class, fn (ClubInvitation $n): bool => !$n->isNewUser);
    }

    public function test_inviting_twice_does_not_duplicate_membership(): void
    {
        $club = Club::factory()->create();
        $invitations = app(ClubInvitations::class);

        $invitations->invite($club, 'Jan', 'jan@example.nl', ClubRole::ClubAdmin);
        $invitations->invite($club, 'Jan', 'jan@example.nl', ClubRole::PlanningCommittee);

        $user = User::firstWhere('email', 'jan@example.nl');
        $this->assertSame(1, $user->clubs()->count());
        $this->assertTrue($user->hasClubRole($club, ClubRole::PlanningCommittee));
        $this->assertFalse($user->hasClubRole($club, ClubRole::ClubAdmin));
    }
}
