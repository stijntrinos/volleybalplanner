<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\Pages\ListTeams;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\DutyTallyService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * The minimum as the yardstick: the calculation, the fair share, the
 * display and the input.
 */
class DutyMinimumTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    private const HALF_1_DATE = '2026-11-14';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
        PlannerSetting::current()->update([
            'points_referee_duty' => 1, 'points_scorer_duty' => 1, 'points_hall_duty' => 2, 'points_volleystars' => 3,
            'hall_duty_weekdays' => [],
            'half_1_start' => '2026-09-01', 'half_1_end' => '2026-12-31',
            'half_2_start' => '2027-01-01', 'half_2_end' => '2027-05-31',
        ]);
    }

    public function test_the_minimum_is_need_times_percentage_rounded(): void
    {
        $this->assertSame(218, (new PointsBalance(312, 70, 0))->minimum());
        $this->assertSame(3, (new PointsBalance(5, 50, 0))->minimum(), 'half a point goes up');
        $this->assertSame(312, (new PointsBalance(312, null, 0))->minimum(), 'an empty percentage means 100%');
    }

    public function test_the_standing_below_and_above_the_minimum(): void
    {
        $below = new PointsBalance(312, 70, 137, 20);
        $this->assertSame(157, $below->total());
        $this->assertSame(61, $below->remaining());
        $this->assertSame(72.0, $below->percentage());
        $this->assertTrue($below->isBelowMinimum());

        $above = new PointsBalance(100, 50, 60);
        $this->assertSame(0, $above->remaining());
        $this->assertSame(10, $above->aboveMinimum());
        $this->assertSame(120.0, $above->percentage());
        $this->assertTrue($above->isAboveTarget());
    }

    public function test_zero_percent_means_no_minimum_and_no_fair_share(): void
    {
        $this->team('HS 4', ['volunteer_points_used' => 312, 'volunteer_points_percentage' => 0]);

        $context = $this->context();
        $team = $context->teamWithCode('HS 4');

        $this->assertFalse($context->balance($team)->hasMinimum());
        $this->assertNull($context->balance($team)->percentage());
        $this->assertNull($context->arrears($team, self::HALF_1_DATE));
    }

    public function test_the_fair_share_follows_what_each_team_still_has_to_fill(): void
    {
        // One match with 400 points of duties together in the first half.
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->venue->id, 'match_type' => MatchType::Home,
            'date' => self::HALF_1_DATE, 'time' => '10:00', 'referee_points' => 200, 'scorer_points' => 200,
        ]);
        $this->team('HS 4', ['volunteer_points_used' => 61]);
        $this->team('HS 5', ['volunteer_points_used' => 1939]);

        $context = $this->context();

        $this->assertEqualsWithDelta(12.2, $context->fairShare($context->teamWithCode('HS 4'), 1), 0.0001);
        $this->assertEqualsWithDelta(12.2, $context->arrears($context->teamWithCode('HS 4'), self::HALF_1_DATE), 0.0001);
    }

    public function test_a_new_need_shows_in_the_overviews_right_away(): void
    {
        $team = $this->team('HS 4', ['volunteer_points_used' => 312, 'volunteer_points_percentage' => 70]);
        $this->assertSame(218, app(DutyTallyService::class)->perTeam($this->version)[0]['minimum']);

        $team->update(['volunteer_points_used' => 400]);

        $this->assertSame(280, app(DutyTallyService::class)->perTeam($this->version)[0]['minimum']);
    }

    public function test_excluding_a_team_from_volunteer_points_means_no_minimum(): void
    {
        $team = $this->team('HS 4', ['volunteer_points_used' => 312, 'volunteer_points_percentage' => 70, 'include_volunteers' => false]);

        $this->assertSame(0, PointsBalance::forTeam($team)->minimum());

        $team->update(['include_volunteers' => true]);

        $this->assertSame(218, PointsBalance::forTeam($team->fresh())->minimum(), 'the values entered before apply again');
    }

    public function test_without_volunteer_points_no_team_has_a_minimum_and_every_duty_counts_one(): void
    {
        $this->setClubFeatures(app(CurrentClub::class)->require(), ['duties']);
        $this->team('HS 4', ['volunteer_points_used' => 312]);
        $this->homeMatch(['scorer' => 'HS 4', 'scorer_points' => 5]);

        $row = app(DutyTallyService::class)->perTeam($this->version)[0];

        $this->assertFalse($row['has_minimum']);
        $this->assertSame(1, $row['total']);
    }

    public function test_a_duty_keeps_its_points_when_the_default_changes(): void
    {
        $match = $this->homeMatch();
        $this->assertSame(1, $match->scorer_points);

        PlannerSetting::current()->update(['points_scorer_duty' => 4]);

        $this->assertSame(1, $match->fresh()->scorer_points);
        $this->assertSame(4, $this->homeMatch(['time' => '14:00'])->scorer_points, 'a new duty gets the new default');
    }

    public function test_the_form_refuses_a_percentage_outside_zero_to_hundred(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $team = $this->team('HS 1');

        foreach ([101, -1] as $wrong) {
            Livewire::test(EditTeam::class, ['record' => $team->getKey()])
                ->fillForm(['volunteer_points_percentage' => $wrong])
                ->call('save')
                ->assertHasFormErrors(['volunteer_points_percentage']);
            $this->assertNull($team->fresh()->volunteer_points_percentage, "{$wrong} should not be saved");
        }

        foreach ([0, 100] as $right) {
            Livewire::test(EditTeam::class, ['record' => $team->getKey()])
                ->fillForm(['volunteer_points_percentage' => $right])
                ->call('save')
                ->assertHasNoFormErrors();
            $this->assertSame($right, $team->fresh()->volunteer_points_percentage);
        }
    }

    public function test_the_standing_tab_shows_the_minimum_and_the_standing(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $team = $this->team('HS 1', ['volunteer_points_used' => 312, 'volunteer_points_percentage' => 70, 'volunteer_points_earned' => 137]);

        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->assertSee('Behoefte')
            ->assertSee('218 punten')
            ->assertSee('Nog in te vullen tot het minimum')
            ->assertDontSee('Ontvangen / verbruikt');
    }

    public function test_the_team_list_shows_a_negative_number_above_the_minimum(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $this->team('HS 1', ['volunteer_points_used' => 100, 'volunteer_points_percentage' => 50, 'volunteer_points_earned' => 60]);
        $this->team('HS 2', ['volunteer_points_used' => 312, 'volunteer_points_percentage' => 70, 'volunteer_points_earned' => 137]);

        Livewire::test(ListTeams::class)
            ->assertSee('Nog in te vullen')
            ->assertSee('-10')
            ->assertSee('Boven doel')
            ->assertSee('81');
    }

    public function test_a_new_team_gets_the_default_exceptions_of_its_class(): void
    {
        $ja = Team::factory()->create(['code' => 'JA 3']);
        $mc = Team::factory()->create(['code' => 'MC 2']);
        $hs = Team::factory()->create(['code' => 'HS 9']);
        $chosen = Team::factory()->create(['code' => 'JA 4', 'referees_c_youth' => false]);

        $this->assertTrue($ja->fresh()->no_hall_duty);
        $this->assertTrue($ja->fresh()->referees_c_youth);
        $this->assertTrue($mc->fresh()->never_referees);
        $this->assertFalse($hs->fresh()->no_hall_duty);
        $this->assertFalse($hs->fresh()->never_referees);
        $this->assertFalse($chosen->fresh()->referees_c_youth, 'an explicit choice wins');
    }

    public function test_the_conversion_moves_the_club_rules_to_the_teams(): void
    {
        $club = app(CurrentClub::class)->require();
        PlannerSetting::current()->update(['duty_rules' => [
            'no_referee' => ['DS 10'],
            'referee_requirements' => ['HS 2' => ['DS 1', 'HS 1', 'HS 3', 'HS 6']],
            'scorer_heavy_teams' => ['HS 1'],
            'hall_duty_codes' => ['HS', 'DS', 'JA', 'MA'],
        ]]);
        foreach (['DS 10', 'DS 1', 'HS 6', 'HS 4', 'JA 1', 'JB 2', 'MC 1'] as $code) {
            DB::table('planner_teams')->insert(['club_id' => $club->id, 'code' => $code, 'name' => 'AETOS '.$code, 'source' => 'manual']);
        }

        $migration = require database_path('migrations/2026_10_27_152924_add_planning_rules_to_planner_teams.php');
        (new ReflectionMethod($migration, 'convertClubRules'))->invoke($migration);

        $team = fn (string $code): Team => Team::query()->where('code', $code)->firstOrFail();
        $this->assertTrue($team('DS 10')->never_referees);
        $this->assertTrue($team('JB 2')->never_referees, 'B and C youth never refereed');
        $this->assertTrue($team('HS 6')->referees_restricted_teams);
        $this->assertFalse($team('HS 4')->referees_restricted_teams);
        $this->assertTrue($team('JA 1')->referees_c_youth);
        $this->assertTrue($team('MC 1')->no_scoring_late);
        $this->assertTrue($team('MC 1')->no_scoring_heavy_teams);
        $this->assertTrue($team('JB 2')->no_hall_duty);
        $this->assertFalse($team('JA 1')->no_hall_duty);

        $rules = PlannerSetting::current()->fresh()->duty_rules;
        $this->assertSame(['HS 2'], $rules['restricted_referee_teams']);
        $this->assertArrayNotHasKey('referee_requirements', $rules);
    }
}
