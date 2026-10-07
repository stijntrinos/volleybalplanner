<?php

namespace Tests\Feature\Planner\Filament;

use App\Models\User;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Activities\ActivityResource;
use App\Planner\Filament\Resources\Availabilities\AvailabilityResource;
use App\Planner\Filament\Resources\CalendarEvents\CalendarEventResource;
use App\Planner\Filament\Resources\ChangeRequests\ChangeRequestResource;
use App\Planner\Filament\Resources\HallDuties\HallDutyResource;
use App\Planner\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
use App\Planner\Filament\Resources\Teams\TeamResource;
use App\Planner\Filament\Resources\Trainings\TrainingResource;
use App\Planner\Filament\Resources\Venues\VenueResource;
use App\Planner\Filament\Resources\Volleystars\VolleystarsBlockResource;
use App\Planner\Filament\Widgets\NewChangeRequests;
use App\Planner\Filament\Widgets\PendingAwayChanges;
use App\Planner\Filament\Widgets\PendingChanges;
use App\Planner\Models\Activity;
use App\Planner\Models\Availability;
use App\Planner\Models\CalendarEvent;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\Court;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Ported from Aetos ZaalcommissieToegangTest + WedstrijdzakenFeatureFlagTest
 * (resource parts): the site flag became per-module switches, the
 * zaalcommissie role became planning_committee. Plus tenancy isolation.
 */
class PlannerResourceAccessTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const ALL_RESOURCES = [
        VenueResource::class, TeamResource::class, CalendarEventResource::class, ActivityResource::class,
        AvailabilityResource::class, TrainingResource::class, ScheduleVersionResource::class,
        HallDutyResource::class, VolleystarsBlockResource::class, ChangeRequestResource::class,
    ];

    public function test_the_planning_committee_can_open_every_resource(): void
    {
        $user = $this->actingAsClubUser();
        $club = app(CurrentClub::class)->require();

        foreach (self::ALL_RESOURCES as $resource) {
            $this->actingAs($user)->get($resource::getUrl('index', tenant: $club))->assertOk();
        }
    }

    public function test_a_member_without_a_role_gets_no_access(): void
    {
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $club = $this->createClub();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->clubs()->attach($club);

        $this->actingAs($user)->get(TeamResource::getUrl('index', tenant: $club))->assertForbidden();
    }

    public function test_the_venue_edit_page_shows_courts_and_import_names(): void
    {
        $user = $this->actingAsClubUser();
        $club = app(CurrentClub::class)->require();
        $venue = Venue::factory()->create();

        $this->actingAs($user)
            ->get(VenueResource::getUrl('edit', ['record' => $venue], tenant: $club))
            ->assertOk()
            ->assertSee('Velden')
            ->assertSee('Importnamen');
    }

    /** @return array<string, array{class-string, PlannerFeature}> */
    public static function moduleResources(): array
    {
        return [
            'hall duties' => [HallDutyResource::class, PlannerFeature::Duties],
            'volleystars' => [VolleystarsBlockResource::class, PlannerFeature::Volleystars],
            'trainings' => [TrainingResource::class, PlannerFeature::Trainings],
            'change requests' => [ChangeRequestResource::class, PlannerFeature::ChangeRequests],
        ];
    }

    #[DataProvider('moduleResources')]
    public function test_a_module_resource_is_hidden_and_forbidden_when_its_module_is_off(string $resource, PlannerFeature $feature): void
    {
        $club = $this->createClub(array_values(array_diff(PlannerFeature::allValues(), [$feature->value])));
        $user = $this->actingAsClubUser(club: $club);

        $this->assertFalse($resource::canAccess());
        $this->assertFalse($resource::shouldRegisterNavigation());
        $this->actingAs($user)->get($resource::getUrl('index', tenant: $club))->assertForbidden();

        // Core resources stay available.
        $this->assertTrue(TeamResource::canAccess());
    }

    #[DataProvider('moduleResources')]
    public function test_a_module_resource_is_available_when_its_module_is_on(string $resource, PlannerFeature $feature): void
    {
        $club = $this->createClub(array_merge([$feature->value], $feature->requires() === [] ? [] : array_map(fn (PlannerFeature $f): string => $f->value, $feature->requires())));
        $user = $this->actingAsClubUser(club: $club);

        $this->assertTrue($resource::shouldRegisterNavigation());
        $this->actingAs($user)->get($resource::getUrl('index', tenant: $club))->assertOk();
    }

    public function test_core_resources_need_no_module(): void
    {
        $club = $this->createClub([]);
        $user = $this->actingAsClubUser(club: $club);

        foreach ([VenueResource::class, TeamResource::class, CalendarEventResource::class, ActivityResource::class,
            AvailabilityResource::class, ScheduleVersionResource::class] as $resource) {
            $this->actingAs($user)->get($resource::getUrl('index', tenant: $club))->assertOk();
        }
    }

    public function test_a_committee_member_of_club_a_cannot_open_records_of_club_b(): void
    {
        $clubB = $this->createClub();
        [$teamB, $venueB] = app(CurrentClub::class)->run($clubB, fn (): array => [
            Team::factory()->create(['code' => 'XB1']),
            Venue::factory()->create(),
        ]);

        $user = $this->actingAsClubUser();
        $clubA = app(CurrentClub::class)->require();

        // Club B's record through club A's panel: not found.
        $this->actingAs($user)->get(TeamResource::getUrl('edit', ['record' => $teamB], tenant: $clubA))->assertNotFound();
        $this->actingAs($user)->get(VenueResource::getUrl('edit', ['record' => $venueB], tenant: $clubA))->assertNotFound();

        // Club B's panel itself: the user is no member.
        $response = $this->actingAs($user)->get(TeamResource::getUrl('index', tenant: $clubB));
        $this->assertContains($response->status(), [403, 404]);

        // And the list of club A does not show club B's team.
        $this->actingAs($user)->get(TeamResource::getUrl('index', tenant: $clubA))->assertOk()->assertDontSee('XB1');
    }

    public function test_a_club_admin_has_access_too(): void
    {
        $user = $this->actingAsClubUser(ClubRole::ClubAdmin);
        $club = app(CurrentClub::class)->require();

        $this->actingAs($user)->get(ScheduleVersionResource::getUrl('index', tenant: $club))->assertOk();
    }

    public function test_the_lists_render_with_records_and_the_dashboard_shows_the_widgets(): void
    {
        $user = $this->actingAsClubUser();
        $club = app(CurrentClub::class)->require();
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create(['is_managed' => true]);
        $team = Team::factory()->create(['default_venue_id' => $venue->id, 'scores_own_match' => true, 'include_in_duties' => true]);
        TeamSource::factory()->create(['team_id' => $team->id]);
        Court::factory()->create(['venue_id' => $venue->id]);
        CalendarEvent::factory()->create();
        Activity::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id]);
        Availability::factory()->create(['venue_id' => $venue->id]);
        Training::factory()->create(['team_id' => $team->id, 'venue_id' => $venue->id]);
        HallDuty::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id, 'team_id' => $team->id]);
        $block = VolleystarsBlock::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id]);
        ChangeRequest::factory()->create(['team_id' => $team->id]);

        foreach (self::ALL_RESOURCES as $resource) {
            $this->actingAs($user)->get($resource::getUrl('index', tenant: $club))->assertOk();
        }

        $this->actingAs($user)->get(TeamResource::getUrl('edit', ['record' => $team], tenant: $club))->assertOk();
        $this->actingAs($user)->get(VolleystarsBlockResource::getUrl('edit', ['record' => $block], tenant: $club))->assertOk();

        // The widgets load lazily, so check they are registered on the panel.
        $this->actingAs($user)->get('/app/'.$club->slug)->assertOk();
        $widgets = Filament::getPanel('club')->getWidgets();
        $this->assertContains(PendingChanges::class, $widgets);
        $this->assertContains(PendingAwayChanges::class, $widgets);
        $this->assertContains(NewChangeRequests::class, $widgets);
    }
}
