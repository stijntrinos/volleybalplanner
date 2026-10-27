<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\RuleEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * Steps 1 and 2 of the planning rules: who can do a duty (hard) and who is
 * not proposed (soft, "Niet voorgesteld: ...").
 */
class DutyRuleEngineTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    private const REFEREE = PlanningContext::DUTY_REFEREE;

    private const SCORER = PlanningContext::DUTY_SCORER;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
    }

    public function test_an_away_match_outside_the_home_city_blocks_a_team_the_whole_day(): void
    {
        $this->venue->update(['city' => 'Arnhem']);
        PlannerSetting::current()->update(['main_venue_id' => $this->venue->id]);
        $team = $this->team('HS 4');
        $this->homeMatch([
            'home_team_name' => 'Dynamo HS 4', 'away_team_name' => 'AETOS HS 4', 'away_team_id' => $team->id,
            'match_type' => MatchType::Away, 'venue_id' => Venue::factory()->create(['city' => 'Apeldoorn'])->id, 'time' => '20:00',
        ]);

        $this->assertContains('Het team speelt die dag uit buiten Arnhem.', $this->violations($team, $this->matchOf('AETOS HS 5'), self::SCORER));
    }

    public function test_without_a_home_city_every_away_match_blocks_the_day(): void
    {
        $team = $this->team('HS 4');
        $this->homeMatch([
            'home_team_name' => 'Dynamo HS 4', 'away_team_name' => 'AETOS HS 4', 'away_team_id' => $team->id,
            'match_type' => MatchType::Away, 'venue_id' => null, 'time' => '20:00',
        ]);

        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $this->violations($team, $this->matchOf('AETOS HS 5'), self::SCORER));
    }

    /** An away match in the home city counts as a match in the own hall: only the time and the venue matter. */
    public function test_an_away_match_in_the_home_city_counts_as_a_match_in_the_own_hall(): void
    {
        PlannerSetting::current()->update(['duty_rules' => ['home_city' => 'Arnhem']]);
        $team = $this->team('HS 4');
        $this->homeMatch([
            'home_team_name' => 'Dynamo HS 4', 'away_team_name' => 'AETOS HS 4', 'away_team_id' => $team->id,
            'match_type' => MatchType::Away, 'venue_id' => Venue::factory()->create(['city' => 'Arnhem'])->id, 'time' => '16:00',
        ]);

        $reasons = $this->violations($team, $this->matchOf('AETOS HS 5'), self::SCORER);

        $this->assertNotContains('Het team speelt die dag uit buiten Arnhem.', $reasons);
        $this->assertContains('Het team speelt die dag in een andere sporthal.', $reasons);
    }

    /**
     * An opponent's team code often looks like ours: "D.V.C. Rebelle MC 1"
     * contains "MC 1". Read as our own team, MC 1 seemed to play two home
     * matches at once -- while a team may score its own match.
     */
    public function test_an_opponent_with_the_same_team_code_is_not_an_own_match(): void
    {
        $mc1 = $this->team('MC 1', ['scores_own_match' => true]);
        $jc2 = $this->team('JC 2');
        $own = $this->homeMatch([
            'home_team_name' => 'AETOS MC 1', 'away_team_name' => 'Trivos MC 1', 'home_team_id' => $mc1->id,
            'time' => '09:00', 'duration_minutes' => 105,
        ]);
        $this->homeMatch([
            'home_team_name' => 'AETOS JC 2', 'away_team_name' => 'D.V.C. Rebelle MC 1', 'home_team_id' => $jc2->id,
            'time' => '09:00', 'duration_minutes' => 105,
        ]);

        $this->assertSame([], $this->violations($mc1, $own, self::SCORER), 'MC 1 may score its own match.');
    }

    public function test_a_club_name_with_a_team_code_before_ours_does_not_block_our_team(): void
    {
        $hs2 = $this->team('HS 2');
        $ja2 = $this->team('JA 2');
        $target = $this->homeMatch([
            'home_team_name' => 'AETOS JA 2', 'away_team_name' => 'Toesj JA 1', 'home_team_id' => $ja2->id,
            'time' => '17:00', 'duration_minutes' => 105,
        ]);
        // The opponent is called "WRA Juristen - Rebelle HS 2"; that HS 2 is
        // not ours, but stands before our own code in the same name.
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'WRA Juristen', 'away_team_name' => 'Rebelle HS 2 - AETOS HS 3',
            'match_type' => MatchType::Away, 'time' => '16:00', 'duration_minutes' => 120,
        ]);

        $this->assertSame([], $this->violations($hs2, $target, self::REFEREE), 'the away match of HS 3 says nothing about HS 2');
    }

    public function test_a_real_second_home_match_does_block(): void
    {
        $team = $this->team('MC 1', ['scores_own_match' => true]);
        $own = $this->homeMatch([
            'home_team_name' => 'AETOS MC 1', 'away_team_name' => 'Trivos MC 1', 'home_team_id' => $team->id,
            'time' => '09:00', 'duration_minutes' => 105,
        ]);
        $this->homeMatch([
            'home_team_name' => 'AETOS MC 1', 'away_team_name' => 'Avior MC 3', 'home_team_id' => $team->id,
            'time' => '09:30', 'duration_minutes' => 105,
        ]);

        $this->assertContains('Het team speelt zelf op het tijdstip van de dienst.', $this->violations($team, $own, self::SCORER));
    }

    public function test_an_away_match_of_another_team_does_not_block_our_team(): void
    {
        $ma2 = $this->team('MA 2');
        $ma6 = $this->team('MA 6');
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Tweestroom MA 2', 'away_team_name' => 'AETOS MA 6',
            'away_team_id' => $ma6->id, 'match_type' => MatchType::Away, 'time' => '10:30',
        ]);

        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $this->violations($ma2, $this->matchOf('AETOS HS 5', ['time' => '12:30']), self::SCORER));
    }

    public function test_a_wrong_old_team_id_on_the_away_match_of_hs8_does_not_block_hs7(): void
    {
        $hs7 = $this->team('HS 7');
        $hs8 = $this->team('HS 8');
        $target = $this->matchOf('AETOS DS 5', ['time' => '20:30', 'duration_minutes' => 120]);
        // An old import wrongly linked this pool match of HS 8 to source team HS 7.
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Boemerang Komeet HS 2', 'away_team_name' => 'AETOS HS 8',
            'away_team_id' => $hs7->id, 'match_type' => MatchType::Away, 'time' => '19:30', 'duration_minutes' => 120,
        ]);

        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs7, $target, self::REFEREE));
        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs8, $target, self::REFEREE));
    }

    public function test_a_training_at_the_time_of_the_duty_is_not_proposed(): void
    {
        $team = $this->team('DS 4');
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => Venue::factory()->create()->id,
            'weekday' => 'zaterdag', 'start_time' => '09:30', 'end_time' => '11:00', 'court' => 1,
        ]);

        $reasons = $this->violations($team, $this->matchOf('AETOS HS 5'), self::SCORER);

        $this->assertContains('Niet voorgesteld: Het team traint op dat moment.', $reasons);
        $this->assertTrue(RuleEngine::isWarning('Niet voorgesteld: Het team traint op dat moment.'));
    }

    /** The training gives way to the match when the team plays at that moment. */
    public function test_a_training_does_not_count_when_the_team_plays_itself(): void
    {
        $hs4 = $this->team('HS 4', ['scores_own_match' => true]);
        $own = $this->homeMatch(['home_team_name' => 'AETOS HS 4', 'home_team_id' => $hs4->id, 'time' => '20:30', 'duration_minutes' => 120]);
        Training::factory()->create([
            'team_id' => $hs4->id, 'venue_id' => $this->venue->id,
            'weekday' => 'zaterdag', 'start_time' => '20:00', 'end_time' => '22:00', 'court' => 1,
        ]);

        $this->assertSame([], $this->violations($hs4, $own, self::SCORER), 'HS 4 may score its own match; the training gives way.');
    }

    public function test_never_referees_and_the_restricted_teams_are_team_settings(): void
    {
        PlannerSetting::current()->update(['duty_rules' => ['restricted_referee_teams' => ['HS 2']]]);
        $ds10 = $this->team('DS 10', ['never_referees' => true]);
        $hs4 = $this->team('HS 4');
        $hs1 = $this->team('HS 1', ['referees_restricted_teams' => true]);
        $target = $this->matchOf('AETOS HS 2');

        $this->assertContains('Niet voorgesteld: Fluit nooit.', $this->violations($ds10, $target, self::REFEREE));
        $this->assertContains('Niet voorgesteld: Bij HS 2 fluiten alleen de teams die daarvoor zijn aangewezen.', $this->violations($hs4, $target, self::REFEREE));
        $this->assertSame([], $this->violations($hs1, $target, self::REFEREE));
    }

    /** Without club settings no team-specific rules apply. */
    public function test_without_settings_no_team_specific_rules_apply(): void
    {
        $hs4 = $this->team('HS 4');

        $this->assertSame([], $this->violations($hs4, $this->matchOf('AETOS HS 2'), self::REFEREE));
    }

    public function test_no_scoring_late_and_at_the_heavy_teams(): void
    {
        PlannerSetting::current()->update(['duty_rules' => ['scorer_heavy_teams' => ['HS 1', 'DS 1']]]);
        $jc1 = $this->team('JC 1', ['no_scoring_late' => true, 'no_scoring_heavy_teams' => true]);

        $this->assertContains('Telt niet bij wedstrijden vanaf 19:00 uur.', $this->violations($jc1, $this->matchOf('AETOS HS 5', ['time' => '19:30']), self::SCORER));
        $this->assertContains('Telt niet bij HS 1 of DS 1.', $this->violations($jc1, $this->matchOf('AETOS DS 1', ['time' => '12:00']), self::SCORER));
    }

    public function test_only_a_team_that_scores_its_own_match_scores_its_own_home_match(): void
    {
        $mb4 = $this->team('MB 4', ['scores_own_match' => true]);
        $mb5 = $this->team('MB 5');

        $this->assertSame([], $this->violations($mb4, $this->matchOf('AETOS MB 4'), self::SCORER));
        $this->assertContains('Het team speelt zelf op het tijdstip van de dienst.', $this->violations($mb5, $this->matchOf('AETOS MB 5', ['time' => '13:00']), self::SCORER));
    }

    public function test_scoring_own_match_stops_after_the_end_date(): void
    {
        $until = CarbonImmutable::parse($this->saturday)->subDay()->toDateString();
        $mb4 = $this->team('MB 4', ['scores_own_match' => true, 'scores_own_match_until' => $until]);

        $this->assertContains('Het team speelt zelf op het tijdstip van de dienst.', $this->violations($mb4, $this->matchOf('AETOS MB 4'), self::SCORER));
    }

    public function test_refereeing_the_own_home_match_stays_blocked(): void
    {
        $team = $this->team('HS 1', ['scores_own_match' => true]);

        $this->assertContains('Het team speelt zelf op het tijdstip van de dienst.', $this->violations($team, $this->matchOf('AETOS HS 1'), self::REFEREE));
    }

    public function test_youth_gets_no_duties_on_weekdays(): void
    {
        $mb3 = $this->team('MB 3');
        $thursday = CarbonImmutable::parse($this->saturday)->subDays(2)->toDateString();

        $this->assertContains(
            'Niet voorgesteld: Jeugdteams krijgen doordeweeks geen diensten.',
            $this->violations($mb3, $this->matchOf('AETOS HS 5', ['date' => $thursday, 'time' => '19:00']), self::SCORER),
        );
    }

    public function test_a_c_youth_referee_preference_keeps_a_youth_off_seniors(): void
    {
        $ja1 = $this->team('JA 1');
        $ja2 = $this->team('JA 2', ['referees_c_youth' => false]);

        $this->assertTrue($ja1->referees_c_youth, 'a new A youth team referees C youth');
        $this->assertContains('Niet voorgesteld: Fluit niet bij A-jeugd en senioren.', $this->violations($ja1, $this->matchOf('AETOS HS 5'), self::REFEREE));
        $this->assertSame([], $this->violations($ja1, $this->matchOf('AETOS JC 1', ['time' => '12:00']), self::REFEREE));
        $this->assertContains(
            "Niet voorgesteld: Van de A-jeugd fluiten alleen teams met 'Fluit bij C-jeugd'.",
            $this->violations($ja2, $this->matchOf('AETOS JC 2', ['time' => '14:00']), self::REFEREE),
        );
    }

    public function test_a_lower_class_women_team_only_referees_youth_and_lower_women(): void
    {
        $ds8 = $this->team('DS 8', ['referees_youth_and_lower_women' => true]);

        $this->assertContains('Niet voorgesteld: Fluit alleen bij jeugd en dames uit een lagere klasse.', $this->violations($ds8, $this->matchOf('AETOS HS 5'), self::REFEREE));
        $this->assertSame([], $this->violations($ds8, $this->matchOf('AETOS DS 7', ['time' => '12:00', 'pool' => 'Dames 3e klasse D']), self::REFEREE));
    }

    public function test_only_a_soft_rule_is_a_warning(): void
    {
        $this->assertTrue(RuleEngine::isWarning('Niet voorgesteld: Fluit nooit.'));
        $this->assertFalse(RuleEngine::isWarning('Het team heeft die dag een uitwedstrijd.'));
        $this->assertFalse(RuleEngine::isWarning('Telt niet bij wedstrijden vanaf 19:00 uur.'));
    }

    public function test_an_excluded_team_is_reported(): void
    {
        $team = $this->team('HS 4', ['include_in_duties' => false]);
        $this->matchOf('AETOS HS 5');
        $context = $this->context();

        $reasons = app(RuleEngine::class)->violations($team, $context->dutyForHallDuty($this->saturday, $this->venue->id, '09:15', '12:15'), $context);

        $this->assertContains('Het team doet niet mee aan de FTZ-planning.', $reasons);
    }
}
