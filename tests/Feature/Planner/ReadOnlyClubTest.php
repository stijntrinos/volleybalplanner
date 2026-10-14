<?php

namespace Tests\Feature\Planner;

use App\Enums\ContactTopic;
use App\Livewire\PlanUpgradePrompt;
use App\Models\Club;
use App\Models\ContactRequest;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Resources\Teams\TeamResource;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use App\Planner\Services\ChangeRequestService;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubIsReadOnly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * After the trial or subscription ends the club is read-only: the planning
 * stays visible, but nothing can be changed until it is extended.
 */
class ReadOnlyClubTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private function expiredClub(): Club
    {
        $club = $this->createClub();
        $club->update(['subscription_starts_at' => now()->subYear(), 'subscription_ends_at' => now()->subDay()]);

        return $club;
    }

    public function test_the_club_turns_read_only_the_day_after_the_period_ends(): void
    {
        $club = $this->createClub();
        $this->assertFalse($club->isReadOnly());

        $club->update(['trial_ends_at' => today()]);
        $this->assertFalse($club->refresh()->isReadOnly());

        $club->update(['trial_ends_at' => today()->subDay()]);
        $this->assertTrue($club->refresh()->isReadOnly());

        // A subscription that started replaces the trial.
        $club->update(['subscription_starts_at' => today()->subDays(3)]);
        $this->assertFalse($club->refresh()->isReadOnly());

        $club->update(['subscription_ends_at' => today()->subDay()]);
        $this->assertTrue($club->refresh()->isReadOnly());
    }

    public function test_everything_stays_visible_but_writes_are_refused(): void
    {
        $club = $this->expiredClub();
        $user = $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->get('/app/'.$club->slug)->assertOk()->assertSee('leesstand');
        $this->get('/app/'.$club->slug.'/teams')->assertOk();
        $this->get('/app/'.$club->slug.'/teams/create')->assertForbidden();

        $this->assertFalse(TeamResource::canCreate());
        $this->assertTrue($user->can('ViewAny:Team'));
        $this->assertFalse($user->can('Update:FixtureMatch'));

        // The safety net under every write path.
        $this->expectException(ClubIsReadOnly::class);
        Team::factory()->create();
    }

    public function test_the_committee_sees_the_read_only_notice_too(): void
    {
        $club = $this->expiredClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get('/app/'.$club->slug)->assertOk()
            ->assertSee('leesstand')
            ->assertSee('Vraag een clubbeheerder');
    }

    public function test_platform_admins_cannot_write_in_a_read_only_club_either(): void
    {
        $club = $this->expiredClub();
        $user = $this->actingAsClubUser(ClubRole::ClubAdmin, $club);
        $user->forceFill(['is_platform_admin' => true])->save();

        $this->assertFalse($user->can('Create:Team'));
        $this->assertTrue($user->can('ViewAny:Team'));
    }

    public function test_dragging_a_match_gives_a_notification_and_changes_nothing(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-19', 'time' => null, 'venue_id' => null, 'court_id' => null,
        ]);
        $club->update(['trial_ends_at' => today()->subWeek()]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->set('date', '2026-09-19')
            ->call('move', $match->id, $venue->id, $court->id, '19:30')
            ->assertNotified('Alleen bekijken');

        $this->assertNull($match->refresh()->venue_id);
    }

    public function test_the_planner_shows_matches_read_only_and_greyed_out(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $match = FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2026-09-19', 'time' => '19:30', 'venue_id' => $venue->id, 'court_id' => $court->id]);
        $club->update(['trial_ends_at' => today()->subWeek()]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->set('date', '2026-09-19')
            ->assertSee('is-readonly', false)
            ->assertDontSee(__('Verplaatsingsassistent'))
            ->call('openMatch', $match->id)
            ->assertSee('Wedstrijd bekijken')
            ->assertDontSee('Opslaan');

        $this->get('/app/'.$club->slug)->assertSee('vp-read-only-tint', false);
        // Where the subscription is handled, the page keeps its colour.
        $this->get('/app/'.$club->slug.'/abonnement')->assertOk()
            ->assertDontSee('vp-read-only-tint', false)
            ->assertSee('Verlenging aanvragen');
    }

    public function test_a_club_admin_requests_the_renewal(): void
    {
        $club = $this->expiredClub();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        Livewire::test(PlanUpgradePrompt::class)
            ->call('show', null, null, true)
            ->assertSee('Abonnement verlengen')
            ->call('requestUpgrade')
            ->assertSet('requested', true);

        $request = ContactRequest::query()->sole();
        $this->assertSame(ContactTopic::Renewal, $request->topic);
        $this->assertStringContainsString('Het abonnement is afgelopen', $request->message);
    }

    public function test_own_preferences_can_still_be_saved(): void
    {
        $club = $this->expiredClub();
        $user = $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        UserPreference::query()->updateOrCreate(['user_id' => $user->id], ['show_trainings' => false]);

        $this->assertDatabaseHas('planner_user_preferences', ['user_id' => $user->id, 'show_trainings' => false]);
    }

    public function test_public_requests_close_and_the_nightly_sync_skips_the_club(): void
    {
        $club = $this->expiredClub();
        $this->useClub($club);

        $this->assertFalse(app(ChangeRequestService::class)->isOpen());

        $this->artisan('planner:sync-nevobo', ['--club' => $club->slug])
            ->expectsOutputToContain('leesstand')
            ->assertSuccessful();
    }

    public function test_extending_the_subscription_lifts_read_only_mode(): void
    {
        $club = $this->expiredClub();
        $club->update(['subscription_ends_at' => now()->addYear()]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->assertTrue(TeamResource::canCreate());
        $this->get('/app/'.$club->slug)->assertOk()->assertDontSee('leesstand');
    }
}
