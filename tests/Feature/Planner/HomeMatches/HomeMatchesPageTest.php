<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The day planner renders for a club without any optional module and for a
 * club with every module, and the parts of a module only show when it is on.
 * Includes the keyboard accessibility check (Aetos ThuiswedstrijdenToegankelijkheidTest).
 */
class HomeMatchesPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const DATE = '2026-10-03';

    public function test_the_page_renders_for_a_club_without_optional_modules(): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $this->dayWithEverything();

        $html = $this->planner($club);

        $this->assertStringContainsString('data-planner', $html);
        $this->assertStringContainsString('W-1', $html);
        $this->assertStringNotContainsString('planner-volleystars-card', $html);
        $this->assertStringNotContainsString('planner-service-column', $html);
        $this->assertStringNotContainsString('planner-training-card', $html);
    }

    public function test_the_page_renders_for_a_club_with_every_module(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $this->dayWithEverything();

        $html = $this->planner($club);

        $this->assertStringContainsString('W-1', $html);
        $this->assertStringContainsString('planner-volleystars-card', $html);
        $this->assertStringContainsString('planner-service-column', $html);
        $this->assertStringContainsString('planner-training-card', $html);
        $this->assertStringContainsString('planner-activity-card', $html);
        $this->assertStringContainsString('data-timetable-start="480"', $html);
    }

    public function test_the_page_renders_without_any_version(): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get(HomeMatches::getUrl(tenant: $club))
            ->assertOk()
            ->assertSee('Nog geen wedstrijdplanning');
    }

    public function test_the_club_colour_is_available_as_css_variable(): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        PlannerSetting::current()->update(['primary_color' => '#123456']);
        ScheduleVersion::factory()->create();

        $this->assertStringContainsString('--planner-primary:#123456', $this->planner($club));
    }

    public function test_built_planner_assets_are_loaded_on_the_panel(): void
    {
        if (!is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Run `npm run build` to check the asset registration.');
        }
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        ScheduleVersion::factory()->create();

        $html = $this->planner($club);

        $this->assertMatchesRegularExpression('#/build/assets/planner-[^"]+\.css#', $html);
        $this->assertMatchesRegularExpression('#/build/assets/planner-[^"]+\.js#', $html);
    }

    public function test_match_cards_can_be_opened_with_the_keyboard(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $this->dayWithEverything();

        $html = $this->planner($club);

        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('wire:keydown.enter="openMatch', $html);
        $this->assertStringContainsString('aria-pressed', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    public function test_a_member_without_the_page_permission_is_refused(): void
    {
        $club = $this->createClub();
        $user = $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $user->removeClubRoles($club);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->get(HomeMatches::getUrl(tenant: $club))->assertForbidden();
    }

    private function dayWithEverything(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        PlannerSetting::current()->update(['main_venue_id' => $venue->id]);
        $team = Team::factory()->create(['code' => 'HS 1']);

        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => self::DATE, 'time' => '10:00', 'match_code' => 'W-1',
            'venue_id' => $venue->id, 'court_id' => $court->id, 'match_type' => MatchType::Home,
        ]);
        Activity::factory()->create([
            'version_id' => $version->id, 'date' => self::DATE, 'start_time' => '08:00', 'end_time' => '09:00',
            'title' => 'Opening', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);
        HallDuty::factory()->create([
            'version_id' => $version->id, 'date' => self::DATE, 'start_time' => '09:00', 'end_time' => '13:00',
            'venue_id' => $venue->id, 'team_id' => $team->id,
        ]);
        VolleystarsBlock::factory()->create([
            'version_id' => $version->id, 'date' => self::DATE, 'venue_id' => $venue->id,
            'setup_start' => '08:00', 'setup_end' => '09:00', 'teardown_start' => '12:00', 'teardown_end' => '13:00',
            'scoring_start' => '09:00', 'scoring_end' => '12:00',
        ]);
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => $venue->id, 'weekday' => 'zaterdag',
            'start_time' => '17:00', 'end_time' => '18:30', 'court' => 1,
        ]);
    }

    private function planner(Club $club): string
    {
        return $this->get(HomeMatches::getUrl(tenant: $club).'?datum='.self::DATE)
            ->assertOk()
            ->getContent();
    }
}
