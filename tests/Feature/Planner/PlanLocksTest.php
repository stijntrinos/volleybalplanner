<?php

namespace Tests\Feature\Planner;

use App\Enums\ContactTopic;
use App\Filament\Club\Pages\Subscription;
use App\Filament\Club\Resources\ClubUsers\Pages\ListClubUsers;
use App\Livewire\PlanUpgradePrompt;
use App\Models\Club;
use App\Models\ContactRequest;
use App\Models\Plan;
use App\Notifications\ContactRequestReceived;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Resources\Teams\Pages\ListTeams;
use App\Planner\Filament\Resources\Teams\TeamResource;
use App\Planner\Filament\Resources\Venues\Pages\EditVenue;
use App\Planner\Filament\Resources\Venues\Pages\ListVenues;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * What a club gets is decided by its plan: modules outside it stay visible
 * but locked, and limits turn the create buttons into the upgrade prompt.
 */
class PlanLocksTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_a_module_outside_the_plan_is_shown_locked_and_stays_refused(): void
    {
        $club = Club::factory()->withFeatures([])->create();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get('/app/'.$club->slug)
            ->assertOk()
            ->assertSee('#upgrade:away_matches', false)
            ->assertSee('#upgrade:change_requests', false);

        $this->assertFalse(AwayMatches::canAccess());
        $this->get('/app/'.$club->slug.'/away-matches')->assertForbidden();
    }

    public function test_a_module_in_the_plan_is_a_normal_link(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get('/app/'.$club->slug)
            ->assertOk()
            ->assertDontSee('href="#upgrade:', false);
    }

    public function test_the_create_button_turns_into_the_upgrade_prompt_at_the_team_limit(): void
    {
        $club = Club::factory()->create(['plan_id' => Plan::factory()->create(['max_teams' => 1])->id]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->assertTrue(TeamResource::canCreate());
        Team::factory()->create();
        $this->assertFalse(TeamResource::canCreate());

        Livewire::test(ListTeams::class)
            ->assertActionHidden('create')
            ->assertActionVisible('createLocked');
        $this->get('/app/'.$club->slug.'/teams/create')->assertForbidden();
    }

    public function test_away_venues_do_not_count_for_the_venue_limit(): void
    {
        $club = Club::factory()->create(['plan_id' => Plan::factory()->create(['max_venues' => 1])->id]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        Venue::factory()->create(['is_managed' => false, 'is_active' => true]);
        Livewire::test(ListVenues::class)->assertActionVisible('create')->assertActionHidden('createLocked');

        Venue::factory()->create(['is_managed' => true, 'is_active' => true]);
        Livewire::test(ListVenues::class)->assertActionHidden('create')->assertActionVisible('createLocked');
    }

    public function test_switching_a_venue_into_the_planner_respects_the_limit(): void
    {
        $club = Club::factory()->create(['plan_id' => Plan::factory()->create(['max_venues' => 1])->id]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);
        $inPlanner = Venue::factory()->create(['is_managed' => true, 'is_active' => true]);
        $away = Venue::factory()->create(['is_managed' => false, 'is_active' => true]);

        Livewire::test(EditVenue::class, ['record' => $away->getRouteKey()])
            ->fillForm(['is_managed' => true])
            ->call('save')
            ->assertHasFormErrors(['is_managed']);
        $this->assertFalse($away->refresh()->is_managed);

        // A venue that already counts can still be edited.
        Livewire::test(EditVenue::class, ['record' => $inPlanner->getRouteKey()])
            ->fillForm(['name' => 'Nieuwe naam'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_inviting_stops_at_the_admin_limit(): void
    {
        $club = Club::factory()->create(['plan_id' => Plan::factory()->create(['max_admins' => 1])->id]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        Livewire::test(ListClubUsers::class)
            ->assertTableActionHidden('invite')
            ->assertTableActionVisible('inviteLocked');
    }

    public function test_the_prompt_names_the_plan_that_unlocks_the_module_and_sends_the_request(): void
    {
        Notification::fake();
        config(['site.contact_recipient' => 'sales@example.test']);
        $club = Club::factory()->onPlan('basis')->create();
        $user = $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        Livewire::test(PlanUpgradePrompt::class)
            ->call('show', feature: 'change_requests')
            ->assertSee('Wijzigingsverzoeken zit niet in je pakket')
            ->assertSee('Vereniging')
            ->call('requestUpgrade')
            ->assertSet('requested', true)
            ->assertSee('Aanvraag verstuurd');

        $request = ContactRequest::sole();
        $this->assertSame(ContactTopic::Upgrade, $request->topic);
        $this->assertSame($user->email, $request->email);
        $this->assertStringContainsString('Gewenst pakket: Vereniging', (string) $request->message);
        Notification::assertSentOnDemand(ContactRequestReceived::class);
    }

    public function test_the_prompt_does_nothing_for_someone_outside_the_club(): void
    {
        $club = Club::factory()->onPlan('basis')->create();
        $this->actingAsClubUser(ClubRole::ClubAdmin);
        $this->useClub($club);

        Livewire::test(PlanUpgradePrompt::class)
            ->assertSet('clubId', $club->id)
            ->call('requestUpgrade');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_club_admins_see_their_subscription(): void
    {
        $club = Club::factory()->onPlan('vereniging')->create();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->get('/app/'.$club->slug.'/abonnement')
            ->assertOk()
            ->assertSee('Vereniging')
            ->assertSee('Zitvolleybal')
            ->assertSee('In Groot');
    }

    public function test_the_largest_plan_offers_no_upgrade(): void
    {
        $club = Club::factory()->onPlan('groot')->create();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->get('/app/'.$club->slug.'/abonnement')
            ->assertOk()
            ->assertSee('Jullie hebben het grootste pakket')
            ->assertDontSee('Upgrade aanvragen');
    }

    public function test_a_trial_or_subscription_that_ends_soon_shows_a_notice_to_club_admins(): void
    {
        $club = Club::factory()->onPlan('basis')->create(['trial_ends_at' => now()->addDays(5)]);
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->get('/app/'.$club->slug)->assertOk()->assertSee('De proefperiode loopt tot');
        $this->get('/app/'.$club->slug.'/abonnement')->assertSee('Proefperiode tot');

        $club->update(['trial_ends_at' => null, 'subscription_starts_at' => now()->subYear(), 'subscription_ends_at' => now()->subDay()]);
        $this->assertSame('danger', $club->refresh()->subscriptionNotice()['tone']);

        $club->update(['subscription_ends_at' => now()->addYear()]);
        $this->assertNull($club->refresh()->subscriptionNotice());
    }

    public function test_the_committee_does_not_see_the_subscription_notice(): void
    {
        $club = Club::factory()->onPlan('basis')->create(['trial_ends_at' => now()->addDays(5)]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get('/app/'.$club->slug)->assertOk()->assertDontSee('De proefperiode loopt tot');
    }

    public function test_settings_show_locked_modules_where_they_belong(): void
    {
        $club = Club::factory()->onPlan('basis')->create();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        $this->get('/app/'.$club->slug.'/settings?section=website-feeds')
            ->assertOk()
            ->assertSee('RSS-feeds zit niet in je pakket; met Vereniging krijg je het erbij.')
            ->assertSee('Zitvolleybal zit niet in je pakket; met Groot krijg je het erbij.');
    }

    public function test_the_planning_committee_does_not_see_the_subscription(): void
    {
        $club = Club::factory()->onPlan('vereniging')->create();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->assertFalse(Subscription::canAccess());
        $this->get('/app/'.$club->slug.'/abonnement')->assertForbidden();
    }
}
