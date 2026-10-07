<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\MatchOverview;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class MatchOverviewTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $published;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00:00'));
        $this->club = $this->useClub($this->createClub());
        $this->published = ScheduleVersion::factory()->create([
            'name' => 'Seizoen 2026–2027',
            'status' => ScheduleVersionStatus::Published,
            'is_published' => true,
            'published_at' => '2026-09-01 12:00:00',
        ]);
    }

    private function url(): string
    {
        return route('planner.public.matches', ['club' => $this->club]);
    }

    public function test_the_page_shows_matches_and_all_duties_from_the_published_planning(): void
    {
        $venue = Venue::factory()->create(['name' => 'Sporthal De Laar', 'city' => 'Arnhem']);
        $team = Team::factory()->create(['code' => 'DS 3', 'name' => 'Dames 3']);

        $this->match([
            'date' => '2026-09-12', 'time' => '19:30', 'match_code' => 'HOME-1',
            'home_team_name' => 'Demo DS 1', 'away_team_name' => 'Orion DS 2',
            'venue_id' => $venue->id, 'referee' => 'HS 2', 'scorer' => 'DS 4',
        ]);
        $this->match([
            'date' => '2026-09-13', 'time' => '14:00', 'match_code' => 'AWAY-1',
            'home_team_name' => 'Trivos HS 1', 'away_team_name' => 'Demo HS 1',
            'match_type' => MatchType::Away,
        ]);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12',
            'start_time' => '18:30', 'end_time' => '22:30', 'team_id' => $team->id, 'venue_id' => $venue->id,
        ]);
        $block = VolleystarsBlock::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12', 'venue_id' => $venue->id,
            'setup_start' => '08:00', 'setup_end' => '08:30', 'teardown_start' => '12:00',
            'teardown_end' => '12:30', 'scoring_start' => '09:00', 'scoring_end' => '12:00',
        ]);
        VolleystarsTask::factory()->create([
            'block_id' => $block->id, 'role' => VolleystarsRole::Setup, 'team_id' => $team->id, 'sort_order' => 1,
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSeeText('Demo DS 1')
            ->assertSeeText('Orion DS 2')
            ->assertSeeText('HS 2')
            ->assertSeeText('DS 4')
            ->assertDontSeeText('Trivos HS 1')
            ->assertSeeText('Zaaldienst · DS 3')
            ->assertSeeText('VolleyStars')
            ->assertSeeText('Opbouwen · DS 3')
            ->assertSeeText('Sporthal De Laar');
    }

    public function test_without_the_duties_module_no_duties_or_officials_are_shown(): void
    {
        $this->club->update(['features' => [PlannerFeature::ChangeRequests->value]]);
        $venue = Venue::factory()->create(['name' => 'Sporthal De Laar']);
        $team = Team::factory()->create(['code' => 'DS 3']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'HOME-1', 'venue_id' => $venue->id, 'referee' => 'REF-TEAM']);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12', 'team_id' => $team->id, 'venue_id' => $venue->id,
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSee('HOME-1')
            ->assertDontSee('REF-TEAM')
            ->assertDontSee('Zaaldienst')
            ->assertDontSee('Fluiten');
    }

    public function test_drafts_and_other_clubs_planning_stay_private(): void
    {
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match(['version_id' => $draft->id, 'match_code' => 'SECRET-DRAFT']);

        $other = $this->useClub($this->createClub());
        $otherVersion = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create(['version_id' => $otherVersion->id, 'date' => '2026-09-12', 'match_code' => 'OTHER-CLUB']);
        $this->useClub($this->club);

        $this->get($this->url())
            ->assertOk()
            ->assertDontSee('SECRET-DRAFT')
            ->assertDontSee('OTHER-CLUB');
        $this->assertNotSame($other->id, $this->club->id);
    }

    public function test_the_overview_never_shows_the_past_and_no_away_matches(): void
    {
        $this->match(['date' => '2026-09-01', 'match_code' => 'PAST']);
        $this->match(['date' => '2026-09-20', 'match_code' => 'HOME-FUTURE']);
        $this->match(['date' => '2026-09-21', 'match_code' => 'AWAY-FUTURE', 'match_type' => MatchType::Away]);

        $this->get($this->url())
            ->assertOk()
            ->assertDontSee('PAST')
            ->assertSee('HOME-FUTURE')
            ->assertDontSee('AWAY-FUTURE');
    }

    public function test_matches_are_grouped_per_venue_by_time_and_court(): void
    {
        $south = Venue::factory()->create(['name' => 'Sporthal Zuid']);
        $north = Venue::factory()->create(['name' => 'Sporthal Noord']);
        $north2 = Court::factory()->create(['venue_id' => $north->id, 'name' => '2', 'sort_order' => 2]);
        $north1 = Court::factory()->create(['venue_id' => $north->id, 'name' => '1', 'sort_order' => 1]);
        $south1 = Court::factory()->create(['venue_id' => $south->id, 'name' => '1', 'sort_order' => 1]);

        foreach ([
            ['time' => '12:00', 'venue' => $south, 'court' => $south1, 'team' => 'Demo Sort Two'],
            ['time' => '12:00', 'venue' => $north, 'court' => $north2, 'team' => 'Demo Sort Four'],
            ['time' => '10:00', 'venue' => $south, 'court' => $south1, 'team' => 'Demo Sort One'],
            ['time' => '12:00', 'venue' => $north, 'court' => $north1, 'team' => 'Demo Sort Three'],
        ] as $index => $data) {
            $this->match([
                'date' => '2026-09-12', 'time' => $data['time'], 'match_code' => 'SORT-'.$index,
                'home_team_name' => $data['team'], 'venue_id' => $data['venue']->id, 'court_id' => $data['court']->id,
            ]);
        }

        // The venue with the earliest match comes first; within a venue time
        // and then court decide the order.
        $this->get($this->url())
            ->assertOk()
            ->assertSeeTextInOrder([
                'Sporthal Zuid', 'Demo Sort One', 'Demo Sort Two',
                'Sporthal Noord', 'Demo Sort Three', 'Demo Sort Four',
            ]);
    }

    public function test_hall_duties_sit_with_their_own_venue(): void
    {
        $north = Venue::factory()->create(['name' => 'Sporthal Noord']);
        $south = Venue::factory()->create(['name' => 'Sporthal Zuid']);
        $teamNorth = Team::factory()->create(['code' => 'HS 3']);
        $teamSouth = Team::factory()->create(['code' => 'JA 2']);

        $this->match(['date' => '2026-09-12', 'time' => '09:00', 'match_code' => 'NORTH-1', 'home_team_name' => 'Demo North One', 'venue_id' => $north->id]);
        $this->match(['date' => '2026-09-12', 'time' => '09:30', 'match_code' => 'SOUTH-1', 'home_team_name' => 'Demo South One', 'venue_id' => $south->id]);
        foreach ([[$north, $teamNorth], [$south, $teamSouth]] as [$venue, $team]) {
            HallDuty::factory()->create([
                'version_id' => $this->published->id, 'date' => '2026-09-12',
                'start_time' => '08:15', 'end_time' => '10:45', 'team_id' => $team->id, 'venue_id' => $venue->id,
            ]);
        }

        $this->get($this->url())
            ->assertOk()
            ->assertSeeTextInOrder([
                'Sporthal Noord', 'Zaaldienst · HS 3', 'Demo North One',
                'Sporthal Zuid', 'Zaaldienst · JA 2', 'Demo South One',
            ]);
    }

    public function test_a_venue_with_only_a_hall_duty_gets_its_own_group(): void
    {
        $venue = Venue::factory()->create(['name' => 'Sporthal Leeg']);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12', 'start_time' => '10:45', 'end_time' => '12:30',
            'team_id' => Team::factory()->create(['code' => 'DS 8'])->id, 'venue_id' => $venue->id,
        ]);

        $this->get($this->url())
            ->assertOk()
            ->assertSeeTextInOrder(['Sporthal Leeg', 'Zaaldienst · DS 8']);
    }

    public function test_the_venue_is_shown_once_above_the_group_and_not_on_every_line(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel', 'city' => 'Arnhem']);
        foreach (range(1, 3) as $number) {
            $this->match(['date' => '2026-09-12', 'time' => '09:0'.$number, 'match_code' => 'VENUE-'.$number, 'venue_id' => $venue->id]);
        }

        $response = $this->get($this->url())->assertOk();

        $this->assertSame(
            1,
            mb_substr_count($response->getContent() ?: '', 'Middachtensingel'),
            'The venue name belongs once above the group, not with every match.',
        );
    }

    public function test_searching_requires_all_separate_words_to_match(): void
    {
        $arnhem = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $zwolle = Venue::factory()->create(['name' => 'Het Anker', 'city' => 'Zwolle']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'IN-ARNHEM', 'home_team_name' => 'Demo DS 1', 'venue_id' => $arnhem->id]);
        $this->match(['date' => '2026-09-12', 'match_code' => 'IN-ZWOLLE', 'home_team_name' => 'Demo DS 1', 'venue_id' => $zwolle->id]);

        Livewire::test(MatchOverview::class)
            ->set('search', 'ds 1 arnhem')
            ->assertSee('IN-ARNHEM')
            ->assertDontSee('IN-ZWOLLE');
    }

    public function test_searching_ignores_spaces_so_ds1_finds_the_team_ds_1(): void
    {
        $this->match(['date' => '2026-09-12', 'match_code' => 'WITH-SPACE', 'home_team_name' => 'Demo DS 1']);

        Livewire::test(MatchOverview::class)
            ->set('search', 'ds1')
            ->assertSee('WITH-SPACE');
    }

    public function test_a_match_on_two_courts_is_called_cc_and_can_be_found_on_that(): void
    {
        $venue = Venue::factory()->create();
        $court1 = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $court2 = Court::factory()->create(['venue_id' => $venue->id, 'name' => '2']);
        $this->match([
            'date' => '2026-09-12', 'match_code' => 'ON-CENTERCOURT', 'venue_id' => $venue->id,
            'court_id' => $court1->id, 'second_court_id' => $court2->id,
        ]);
        $this->match(['date' => '2026-09-12', 'match_code' => 'ON-ONE-COURT', 'venue_id' => $venue->id, 'court_id' => $court1->id]);

        Livewire::test(MatchOverview::class)
            ->assertSee('CC')
            ->assertDontSee('Veld 1 + 2')
            ->set('search', 'cc')
            ->assertSee('ON-CENTERCOURT')
            ->assertDontSee('ON-ONE-COURT')
            ->set('search', 'centercourt')
            ->assertSee('ON-CENTERCOURT');
    }

    public function test_searching_ignores_accents(): void
    {
        $this->match(['date' => '2026-09-12', 'match_code' => 'WITH-ACCENT', 'away_team_name' => 'Peçé Zwolle']);

        Livewire::test(MatchOverview::class)
            ->set('search', 'pece')
            ->assertSee('WITH-ACCENT');
    }

    public function test_searching_for_dames_finds_the_ds_teams(): void
    {
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-DS', 'home_team_name' => 'Demo DS 3', 'away_team_name' => 'Orion DS 2']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-HS', 'home_team_name' => 'Demo HS 1', 'away_team_name' => 'Orion HS 2']);

        Livewire::test(MatchOverview::class)
            ->set('search', 'dames')
            ->assertSee('SEARCH-DS')
            ->assertDontSee('SEARCH-HS');
    }

    public function test_searching_for_heren_finds_the_hs_teams(): void
    {
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-DS', 'home_team_name' => 'Demo DS 3', 'away_team_name' => 'Orion DS 2']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-HS', 'home_team_name' => 'Demo HS 1', 'away_team_name' => 'Orion HS 2']);

        Livewire::test(MatchOverview::class)
            ->set('search', 'heren')
            ->assertSee('SEARCH-HS')
            ->assertDontSee('SEARCH-DS');
    }

    public function test_searching_for_dames_still_combines_with_other_words(): void
    {
        $laar = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $anker = Venue::factory()->create(['name' => 'Het Anker', 'city' => 'Zwolle']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-ARNHEM', 'home_team_name' => 'Demo DS 3', 'venue_id' => $laar->id]);
        $this->match(['date' => '2026-09-12', 'match_code' => 'SEARCH-ZWOLLE', 'home_team_name' => 'Demo DS 4', 'venue_id' => $anker->id]);

        Livewire::test(MatchOverview::class)
            ->set('search', 'dames arnhem')
            ->assertSee('SEARCH-ARNHEM')
            ->assertDontSee('SEARCH-ZWOLLE');
    }

    public function test_the_team_filter_also_shows_that_teams_hall_duty(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $ds1 = Team::factory()->create(['code' => 'DS 1', 'name' => 'Dames 1']);
        $ds10 = Team::factory()->create(['code' => 'DS 10', 'name' => 'Dames 10']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'OF-DS1', 'home_team_name' => 'Demo DS 1', 'venue_id' => $venue->id]);
        $this->match(['date' => '2026-09-12', 'match_code' => 'OF-DS10', 'home_team_name' => 'Demo DS 10', 'venue_id' => $venue->id]);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12', 'start_time' => '18:30', 'end_time' => '22:30',
            'team_id' => $ds1->id, 'venue_id' => $venue->id, 'notes' => 'DUTY-DS1',
        ]);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'date' => '2026-09-12', 'start_time' => '08:30', 'end_time' => '12:30',
            'team_id' => $ds10->id, 'venue_id' => $venue->id, 'notes' => 'DUTY-DS10',
        ]);

        Livewire::test(MatchOverview::class)
            ->set('team', 'Demo DS 1')
            ->assertSee('OF-DS1')
            ->assertSee('DUTY-DS1')
            ->assertDontSee('OF-DS10')
            ->assertDontSee('DUTY-DS10');
    }

    public function test_the_venue_filter_only_appears_with_several_venues(): void
    {
        $one = Venue::factory()->create(['name' => 'De Laar']);
        $this->match(['date' => '2026-09-12', 'venue_id' => $one->id]);

        Livewire::test(MatchOverview::class)->assertDontSee('Sporthal filteren');

        $second = Venue::factory()->create(['name' => 'Het Anker']);
        $this->match(['date' => '2026-09-13', 'venue_id' => $second->id]);

        Livewire::test(MatchOverview::class)->assertSee('Sporthal filteren');
    }

    public function test_the_venue_filter_limits_the_matches(): void
    {
        $laar = Venue::factory()->create(['name' => 'De Laar']);
        $anker = Venue::factory()->create(['name' => 'Het Anker']);
        $this->match(['date' => '2026-09-12', 'match_code' => 'IN-LAAR', 'venue_id' => $laar->id]);
        $this->match(['date' => '2026-09-12', 'match_code' => 'IN-ANKER', 'venue_id' => $anker->id]);

        Livewire::test(MatchOverview::class)
            ->set('venue', (string) $laar->id)
            ->assertSee('IN-LAAR')
            ->assertDontSee('IN-ANKER');
    }

    public function test_the_month_picker_starts_at_the_next_month_with_matches(): void
    {
        $this->match(['date' => '2026-10-10', 'match_code' => 'IN-OCTOBER']);
        $this->match(['date' => '2026-12-05', 'match_code' => 'IN-DECEMBER']);

        Livewire::test(MatchOverview::class)
            ->assertSet('month', '2026-10')
            ->assertSee('IN-OCTOBER')
            ->assertDontSee('IN-DECEMBER')
            ->assertSee('Oktober 2026')
            ->assertSee('December 2026')
            // September has nothing, so its button should not be there.
            ->assertDontSee('September 2026');
    }

    public function test_choosing_another_month_shows_only_that_month(): void
    {
        $this->match(['date' => '2026-10-10', 'match_code' => 'IN-OCTOBER']);
        $this->match(['date' => '2026-12-05', 'match_code' => 'IN-DECEMBER']);

        Livewire::test(MatchOverview::class)
            ->set('month', '2026-12')
            ->assertSee('IN-DECEMBER')
            ->assertDontSee('IN-OCTOBER');
    }

    public function test_searching_jumps_to_the_first_month_with_results(): void
    {
        $this->match(['date' => '2026-10-10', 'match_code' => 'IN-OCTOBER', 'away_team_name' => 'Orion DS 2']);
        $this->match(['date' => '2026-12-05', 'match_code' => 'IN-DECEMBER', 'away_team_name' => 'Sudosa DS 1']);

        // The active month is October, but the result is in December.
        Livewire::test(MatchOverview::class)
            ->assertSet('month', '2026-10')
            ->set('search', 'sudosa')
            ->assertSet('month', '2026-12')
            ->assertSee('IN-DECEMBER');
    }

    public function test_clearing_resets_all_filters(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $this->match(['date' => '2026-10-10', 'match_code' => 'IN-OCTOBER', 'venue_id' => $venue->id]);

        Livewire::test(MatchOverview::class)
            ->set('search', 'nothing to find')
            ->set('team', 'Demo DS 1')
            ->set('venue', (string) $venue->id)
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('team', '')
            ->assertSet('venue', '')
            ->assertSee('IN-OCTOBER');
    }

    public function test_the_page_does_not_exist_for_an_inactive_club(): void
    {
        $this->club->update(['is_active' => false]);

        $this->get($this->url())->assertNotFound();
    }

    /** @param  array<string, mixed>  $data */
    private function match(array $data): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->published->id,
            'match_type' => MatchType::Home,
            ...$data,
        ]);
    }
}
