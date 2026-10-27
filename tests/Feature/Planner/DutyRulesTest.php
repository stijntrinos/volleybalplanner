<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\PlannerSetting;
use App\Planner\Services\Duties\DutyPlanner;
use App\Planner\Services\Duties\DutyRules;
use App\Planner\Services\Duties\PlanningContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * The duty rules must work for any club: with its own team name pattern,
 * with no pattern at all and with no configured rules.
 */
class DutyRulesTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    public function test_the_defaults_have_no_team_specific_rules(): void
    {
        $rules = new DutyRules;

        $this->assertSame([], $rules->restrictedRefereeTeams());
        $this->assertSame([], $rules->scorerHeavyTeams());
        $this->assertSame('19:00', $rules->scorerCutoff());
        $this->assertSame('', $rules->homeCity());
    }

    public function test_an_emptied_list_means_nobody_and_missing_keys_fall_back(): void
    {
        $rules = new DutyRules(['restricted_referee_teams' => [], 'scorer_cutoff' => null, 'scorer_heavy_teams' => ['HS 1', 'ds-1'], 'no_referee' => ['DS 10']]);

        $this->assertSame([], $rules->restrictedRefereeTeams());
        $this->assertSame('19:00', $rules->scorerCutoff());
        $this->assertSame(['HS1', 'DS1'], $rules->scorerHeavyTeams());
        $this->assertArrayNotHasKey('no_referee', $rules->toArray(), 'old club rules are ignored');
    }

    public function test_codes_read_back_as_text(): void
    {
        $this->assertSame('DS 10', DutyRules::readable('DS10'));
        $this->assertSame('DS 1, HS 1 of HS 3', DutyRules::enumerate(['DS1', 'HS1', 'HS3']));
        $this->assertSame('DS 1', DutyRules::enumerate(['DS1']));
    }

    public function test_rules_work_with_another_club_name_pattern(): void
    {
        $this->setUpDutyClub(['Dynamo', 'DYN']);
        PlannerSetting::current()->update(['duty_rules' => [
            'restricted_referee_teams' => ['HS 1'],
            'scorer_heavy_teams' => ['HS 1'],
        ]]);
        $ds3 = $this->team('DS 3', ['never_referees' => true]);
        $hs4 = $this->team('HS 4');
        $jc1 = $this->team('JC 1', ['no_scoring_heavy_teams' => true]);
        $target = $this->matchOf('DYN HS 1');

        $this->assertContains('Niet voorgesteld: Fluit nooit.', $this->violations($ds3, $target, PlanningContext::DUTY_REFEREE));
        $this->assertContains('Niet voorgesteld: Bij HS 1 fluiten alleen de teams die daarvoor zijn aangewezen.', $this->violations($hs4, $target, PlanningContext::DUTY_REFEREE));
        $this->assertContains('Telt niet bij HS 1.', $this->violations($jc1, $target, PlanningContext::DUTY_SCORER));
    }

    public function test_another_club_name_pattern_recognises_own_matches_by_name(): void
    {
        $this->setUpDutyClub(['Dynamo']);
        $hs4 = $this->team('HS 4');
        $hs5 = $this->team('HS 5');
        // An old wrong team id: the name says HS 5 plays away, not HS 4.
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Orion HS 2', 'away_team_name' => 'Dynamo HS 5',
            'away_team_id' => $hs4->id, 'match_type' => MatchType::Away, 'time' => '14:00',
        ]);
        $target = $this->matchOf('Dynamo HS 9');

        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs4, $target, PlanningContext::DUTY_SCORER));
        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs5, $target, PlanningContext::DUTY_SCORER));
    }

    public function test_the_aetos_pattern_means_nothing_to_another_club(): void
    {
        $this->setUpDutyClub(['Dynamo']);
        PlannerSetting::current()->update(['duty_rules' => ['restricted_referee_teams' => ['HS 1']]]);
        $hs4 = $this->team('HS 4');

        // "AETOS HS 1" is an opponent here, so its restriction does not apply.
        $this->assertSame([], $this->violations($hs4, $this->matchOf('AETOS HS 1'), PlanningContext::DUTY_REFEREE));
    }

    public function test_without_any_name_pattern_teams_are_matched_by_id(): void
    {
        $this->setUpDutyClub([]);
        $hs4 = $this->team('HS 4');
        $hs5 = $this->team('HS 5');
        $this->homeMatch([
            'venue_id' => null, 'home_team_name' => 'Orion HS 2', 'away_team_name' => 'Club HS 4',
            'away_team_id' => $hs4->id, 'match_type' => MatchType::Away, 'time' => '14:00',
        ]);
        $target = $this->matchOf('Club HS 9');

        $this->assertContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs4, $target, PlanningContext::DUTY_SCORER));
        $this->assertNotContains('Het team heeft die dag een uitwedstrijd.', $this->violations($hs5, $target, PlanningContext::DUTY_SCORER));
    }

    public function test_the_planner_generates_a_full_draft_with_empty_rules_and_another_pattern(): void
    {
        $this->setUpDutyClub(['Dynamo']);
        $this->team('HS 4', ['name' => 'Dynamo HS 4']);
        $this->team('DS 2', ['name' => 'Dynamo DS 2']);
        $this->team('DS 10', ['name' => 'Dynamo DS 10']);
        $this->homeMatch(['home_team_name' => 'Dynamo HS 2']);

        $planner = app(DutyPlanner::class);
        $draft = $planner->generate($this->version);

        $this->assertNotNull($draft['matches'][0]['referee']);
        $this->assertNotNull($draft['matches'][0]['scorer']);
        $this->assertNotNull($draft['hall_duties'][0]['team_code']);
        $this->assertSame(0, $planner->validate($this->version, $draft)['count']);
    }
}
