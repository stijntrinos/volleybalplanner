<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\RuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

class DutyRuleEngineTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
    }

    public function test_an_away_match_blocks_a_team_the_whole_day(): void
    {
        $team = $this->team('HS 4');
        $this->homeMatch([
            'home_team_name' => 'Dynamo HS 4', 'away_team_name' => 'AETOS HS 4', 'away_team_id' => $team->id,
            'match_type' => MatchType::Away, 'venue_id' => null, 'time' => '20:00',
        ]);

        $reasons = $this->violations($team, DutyType::Scorer, [
            'date' => $this->saturday, 'start' => '10:00', 'end' => '12:00', 'venue_id' => $this->venue->id,
        ]);

        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $reasons);
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

        $reasons = $this->violations($mc1, DutyType::Scorer, [
            'date' => $this->saturday, 'start' => '09:00', 'end' => '10:45', 'venue_id' => $this->venue->id, 'match' => $own,
        ]);

        $this->assertSame([], $reasons, 'MC 1 may score its own match.');
    }

    public function test_a_club_name_with_a_team_code_before_ours_does_not_block_our_team(): void
    {
        $hs2 = $this->team('HS 2');
        $ja2 = $this->team('JA 2');
        $target = $this->homeMatch([
            'home_team_name' => 'AETOS JA 2', 'away_team_name' => 'Toesj JA 1', 'home_team_id' => $ja2->id,
            'date' => '2026-10-03', 'time' => '17:00', 'duration_minutes' => 105,
        ]);
        // The opponent is called "WRA Juristen - Rebelle HS 2"; that HS 2 is
        // not ours, but stands before our own code in the same name.
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'WRA Juristen', 'away_team_name' => 'Rebelle HS 2 - AETOS HS 3',
            'match_type' => MatchType::Away, 'date' => '2026-10-03', 'time' => '16:00', 'duration_minutes' => 120,
        ]);

        $reasons = $this->violations($hs2, DutyType::Referee, [
            'date' => '2026-10-03', 'start' => '17:00', 'end' => '18:45', 'venue_id' => $this->venue->id, 'match' => $target,
        ]);

        $this->assertSame([], $reasons, 'the away match of HS 3 says nothing about HS 2');
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

        $reasons = $this->violations($team, DutyType::Scorer, [
            'date' => $this->saturday, 'start' => '09:00', 'end' => '10:45', 'venue_id' => $this->venue->id, 'match' => $own,
        ]);

        $this->assertContains('De taak overlapt met de eigen thuiswedstrijd.', $reasons);
    }

    public function test_an_away_match_of_another_team_does_not_block_our_team(): void
    {
        $ma2 = $this->team('MA 2');
        $ma6 = $this->team('MA 6');
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Tweestroom MA 2', 'away_team_name' => 'AETOS MA 6',
            'away_team_id' => $ma6->id, 'match_type' => MatchType::Away, 'time' => '10:30',
        ]);

        $reasons = $this->violations($ma2, DutyType::Scorer, [
            'date' => $this->saturday, 'start' => '12:30', 'end' => '14:15', 'venue_id' => $this->venue->id,
        ]);

        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $reasons);
    }

    public function test_a_wrong_old_team_id_on_the_away_match_of_hs8_does_not_block_hs7(): void
    {
        $hs7 = $this->team('HS 7');
        $hs8 = $this->team('HS 8');
        $ds5 = $this->team('DS 5');
        $target = $this->homeMatch([
            'home_team_name' => 'AETOS DS 5', 'away_team_name' => "ACE'19 DS 1", 'home_team_id' => $ds5->id,
            'date' => '2026-09-24', 'time' => '20:30', 'duration_minutes' => 120,
        ]);
        // An old import wrongly linked this pool match of HS 8 to source team HS 7.
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Boemerang Komeet HS 2', 'away_team_name' => 'AETOS HS 8',
            'away_team_id' => $hs7->id, 'match_type' => MatchType::Away,
            'date' => '2026-09-24', 'time' => '19:30', 'duration_minutes' => 120,
        ]);
        $this->homeMatch([
            'home_team_name' => 'AETOS HS 7', 'away_team_name' => 'Woudenberg HS 4', 'home_team_id' => $hs7->id,
            'date' => '2026-09-25', 'time' => '21:00', 'duration_minutes' => 120,
        ]);
        $slot = ['date' => '2026-09-24', 'start' => '20:30', 'end' => '22:30', 'venue_id' => $this->venue->id, 'match' => $target];

        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs7, DutyType::Referee, $slot));
        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs8, DutyType::Referee, $slot));
    }

    public function test_training_in_another_venue_blocks_a_duty(): void
    {
        $otherVenue = Venue::factory()->create();
        $team = $this->team('DS 4');
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => $otherVenue->id,
            'weekday' => 'zaterdag', 'start_time' => '09:00', 'end_time' => '10:00', 'court' => 1,
        ]);

        $reasons = app(RuleEngine::class)->violations($team, DutyType::HallDuty, [
            'date' => $this->saturday, 'start' => '14:00', 'end' => '16:00', 'venue_id' => $this->venue->id,
        ], $this->version->matches()->get(), Training::all());

        $this->assertContains('Het team traint die dag in een andere sporthal.', $reasons);
    }

    /** Without settings no team-specific rules apply: the Aetos defaults are gone. */
    public function test_without_settings_no_team_specific_rules_apply(): void
    {
        $ds10 = $this->team('DS 10');
        $hs4 = $this->team('HS 4');
        $slot = $this->slot('AETOS HS 2');

        $this->assertSame([], $this->violations($ds10, DutyType::Referee, $slot, false));
        $this->assertSame([], $this->violations($hs4, DutyType::Referee, $slot, false));
    }

    public function test_the_former_hardcoded_rules_work_when_configured(): void
    {
        PlannerSetting::current()->update(['duty_rules' => [
            'no_referee' => ['DS 10'],
            'referee_requirements' => ['HS 2' => ['DS 1', 'HS 1', 'HS 3']],
        ]]);
        $ds10 = $this->team('DS 10');
        $hs4 = $this->team('HS 4');
        $slot = $this->slot('AETOS HS 2');

        $this->assertContains('DS 10 fluit niet.', $this->violations($ds10, DutyType::Referee, $slot, false));
        $this->assertContains(
            'HS 2 mag alleen door DS 1, HS 1 of HS 3 worden gefloten.',
            $this->violations($hs4, DutyType::Referee, $slot, false),
        );
    }

    /** Team line-ups change every season; these rules come from the planner settings. */
    public function test_referee_rules_come_from_the_planner_settings(): void
    {
        PlannerSetting::current()->update(['duty_rules' => [
            'no_referee' => ['DS 8'],
            'referee_requirements' => ['HS 5' => ['DS 1']],
        ]]);
        $ds8 = $this->team('DS 8');
        $ds10 = $this->team('DS 10');
        $hs4 = $this->team('HS 4');
        $slot = $this->slot('AETOS HS 5');

        $this->assertContains('DS 8 fluit niet.', $this->violations($ds8, DutyType::Referee, $slot, false));
        $this->assertNotContains('DS 10 fluit niet.', $this->violations($ds10, DutyType::Referee, $slot, false));
        $this->assertContains('HS 5 mag alleen door DS 1 worden gefloten.', $this->violations($hs4, DutyType::Referee, $slot, false));
    }

    /** Team codes start with the category letter (JC 1, MC 1); the old check on "C" never fired. */
    public function test_c_youth_does_not_score_in_the_evening(): void
    {
        $jc1 = $this->team('JC 1');
        $slot = ['start' => '19:30', 'end' => '21:15'] + $this->slot('AETOS HS 5');

        $this->assertContains('C-jeugd mag niet tellen vanaf 19:00.', $this->violations($jc1, DutyType::Scorer, $slot, false));
    }

    public function test_a_team_may_score_its_own_home_match(): void
    {
        $team = $this->team('MB 4', ['name' => 'AETOS MB 4']);

        $this->assertSame([], $this->violations($team, DutyType::Scorer, $this->slot('AETOS MB 4')));
    }

    public function test_scoring_another_simultaneous_match_stays_blocked(): void
    {
        $team = $this->team('MB 4', ['name' => 'AETOS MB 4']);
        $this->homeMatch(['match_code' => 'OWN-MB4', 'home_team_name' => 'AETOS MB 4']);

        $reasons = $this->violations($team, DutyType::Scorer, $this->slot('AETOS HS 5'));

        $this->assertContains('De taak overlapt met de eigen thuiswedstrijd.', $reasons);
    }

    public function test_refereeing_the_own_home_match_stays_blocked(): void
    {
        $team = $this->team('HS 1', ['name' => 'AETOS HS 1']);

        $reasons = $this->violations($team, DutyType::Referee, $this->slot('AETOS HS 1'));

        $this->assertContains('De taak overlapt met de eigen thuiswedstrijd.', $reasons);
    }

    /** The training gives way to the match when the team plays at that moment. */
    public function test_a_training_does_not_count_when_the_team_plays_itself(): void
    {
        $hs4 = $this->team('HS 4', ['name' => 'AETOS HS 4']);
        $own = $this->homeMatch([
            'home_team_name' => 'AETOS HS 4', 'away_team_name' => 'Rensa Family Orion HS 5', 'home_team_id' => $hs4->id,
            'date' => '2026-09-24', 'time' => '20:30', 'duration_minutes' => 120,
        ]);
        Training::factory()->create([
            'team_id' => $hs4->id, 'venue_id' => $this->venue->id,
            'weekday' => 'donderdag', 'start_time' => '20:00', 'end_time' => '22:00', 'court' => 1,
        ]);

        $reasons = app(RuleEngine::class)->violations($hs4, DutyType::Scorer, [
            'date' => '2026-09-24', 'start' => '20:30', 'end' => '22:30', 'venue_id' => $this->venue->id, 'match' => $own,
        ], $this->version->matches()->get(), Training::all());

        $this->assertSame([], $reasons, 'HS 4 may score its own match; the training gives way.');
    }

    public function test_a_training_still_blocks_when_the_team_does_not_play_itself(): void
    {
        $ds3 = $this->team('DS 3', ['name' => 'AETOS DS 3']);
        $hs4 = $this->team('HS 4', ['name' => 'AETOS HS 4']);
        $match = $this->homeMatch([
            'home_team_name' => 'AETOS HS 4', 'away_team_name' => 'Rensa Family Orion HS 5', 'home_team_id' => $hs4->id,
            'date' => '2026-09-24', 'time' => '20:30', 'duration_minutes' => 120,
        ]);
        Training::factory()->create([
            'team_id' => $ds3->id, 'venue_id' => $this->venue->id,
            'weekday' => 'donderdag', 'start_time' => '20:00', 'end_time' => '22:00', 'court' => 1,
        ]);

        $reasons = app(RuleEngine::class)->violations($ds3, DutyType::Scorer, [
            'date' => '2026-09-24', 'start' => '20:30', 'end' => '22:30', 'venue_id' => $this->venue->id, 'match' => $match,
        ], $this->version->matches()->get(), Training::all());

        $this->assertContains('De taak overlapt met de training.', $reasons);
    }

    /** The C youth time limit is a club preference the committee may overrule. */
    public function test_the_c_youth_time_limit_is_a_warning(): void
    {
        $this->assertTrue(RuleEngine::isWarning('C-jeugd mag niet tellen vanaf 19:00.'));
        $this->assertFalse(RuleEngine::isWarning('Het team heeft die dag een uitwedstrijd.'));
        $this->assertFalse(RuleEngine::isWarning('C-jeugd mag niet tellen bij HS 1.'));
    }

    public function test_an_excluded_team_is_reported(): void
    {
        $team = $this->team('HS 4', ['include_in_duties' => false]);

        $this->assertContains('Team is uitgesloten van de FTZ-planner.', $this->violations($team, DutyType::HallDuty, $this->slot('AETOS HS 5')));
    }
}
