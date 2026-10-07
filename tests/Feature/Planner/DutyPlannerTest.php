<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\PlannerSetting;
use App\Planner\Services\Duties\DutyPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

class DutyPlannerTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
    }

    public function test_an_exception_date_gets_no_hall_duty(): void
    {
        $this->team('HS 4', ['name' => 'AETOS HS 4']);
        // Saturday 12-09 is a regular hall duty day; 19-09 is skipped.
        PlannerSetting::current()->update(['hall_duty_weekdays' => [6], 'hall_duty_exceptions' => [$this->saturdayInWeeks(3)]]);
        foreach ([$this->saturday, $this->saturdayInWeeks(3)] as $date) {
            $this->homeMatch(['date' => $date]);
        }

        $draft = $this->planner()->generate($this->version);

        $this->assertCount(2, $draft['matches'], 'the matches themselves are still assigned');
        $this->assertCount(1, $draft['hall_duties']);
        $this->assertSame($this->saturday, $draft['hall_duties'][0]['date']);
    }

    public function test_a_day_in_the_past_is_not_filled_again(): void
    {
        $this->team('HS 4', ['name' => 'AETOS HS 4']);
        PlannerSetting::current()->update(['hall_duty_weekdays' => [0, 1, 2, 3, 4, 5, 6]]);
        $past = now()->subWeek()->toDateString();
        $upcoming = now()->addWeek()->toDateString();
        foreach ([$past, $upcoming] as $date) {
            $this->homeMatch(['date' => $date]);
        }

        $draft = $this->planner()->generate($this->version);

        $played = collect($draft['matches'])->firstWhere('date', $past);
        $coming = collect($draft['matches'])->firstWhere('date', $upcoming);
        $this->assertNotNull($played, 'the played match stays in the overview');
        $this->assertNull($played['referee'], 'nothing is proposed for a played day');
        $this->assertNull($played['scorer']);
        $this->assertNotNull($coming['referee'], 'the coming day is filled');
        $this->assertSame([$upcoming], collect($draft['hall_duties'])->pluck('date')->all());
    }

    public function test_generates_duties_and_hall_duty_per_playing_round(): void
    {
        $this->team('HS 4', ['name' => 'AETOS HS 4']);
        $this->team('DS 2', ['name' => 'AETOS DS 2']);
        $this->homeMatch();

        $draft = $this->planner()->generate($this->version);

        $this->assertCount(1, $draft['matches']);
        $this->assertNotNull($draft['matches'][0]['referee']);
        $this->assertNotNull($draft['matches'][0]['scorer']);
        $this->assertCount(1, $draft['hall_duties']);
        $this->assertSame('09:15', $draft['hall_duties'][0]['start']);
    }

    public function test_no_hall_duty_when_no_days_are_configured(): void
    {
        PlannerSetting::current()->update(['hall_duty_weekdays' => []]);
        $this->team('HS 4');
        $this->homeMatch();

        $draft = $this->planner()->generate($this->version);

        $this->assertCount(1, $draft['matches']);
        $this->assertSame([], $draft['hall_duties']);
    }

    public function test_fill_keeps_existing_assignments_despite_team_defaults(): void
    {
        $homeTeam = $this->team('HS 2', ['provides_referee' => true, 'scores_own_match' => true]);
        $match = $this->homeMatch([
            'home_team_id' => $homeTeam->id, 'referee' => 'DS 1', 'scorer' => 'HS 4',
            'referee_auto' => false, 'scorer_auto' => false,
        ]);

        $row = $this->planner()->generate($this->version, DutyPlanner::MODE_FILL)['matches'][0];

        $this->assertSame($match->referee, $row['referee']);
        $this->assertSame($match->scorer, $row['scorer']);
        $this->assertTrue($row['locked']['referee']);
        $this->assertTrue($row['locked']['scorer']);
    }

    public function test_redistribute_applies_the_current_own_scorer_setting(): void
    {
        $homeTeam = $this->team('JC 1', ['scores_own_match' => true]);
        $this->homeMatch(['home_team_id' => $homeTeam->id, 'scorer' => 'DS 1', 'scorer_auto' => true]);

        $row = $this->planner()->generate($this->version, DutyPlanner::MODE_REDISTRIBUTE)['matches'][0];

        $this->assertSame('JC 1', $row['scorer']);
        $this->assertFalse($row['locked']['scorer']);
    }

    public function test_skips_sitting_volleyball(): void
    {
        $this->team('HS 4');
        $this->homeMatch(['is_sitting_volleyball' => true]);

        $draft = $this->planner()->generate($this->version);

        $this->assertSame([], $draft['matches']);
        $this->assertSame([], $draft['hall_duties']);
    }

    public function test_only_generates_within_the_chosen_period(): void
    {
        $this->team('HS 4');
        foreach ([$this->saturday, $this->saturdayInWeeks(3), $this->saturdayInWeeks(4)] as $index => $date) {
            $this->homeMatch(['match_code' => 'PERIOD-'.$index, 'date' => $date]);
        }

        $draft = $this->planner()->generate($this->version, from: $this->saturdayInWeeks(3), to: $this->saturdayInWeeks(3));

        $this->assertCount(1, $draft['matches']);
        $this->assertSame($this->saturdayInWeeks(3), $draft['matches'][0]['date']);
        $this->assertCount(1, $draft['hall_duties']);
    }

    public function test_validation_flags_an_invalid_manual_assignment(): void
    {
        $this->team('JB 1', ['name' => 'AETOS JB 1']);
        $match = $this->homeMatch();

        $validation = $this->planner()->validate($this->version, [
            'matches' => [['id' => $match->id, 'referee' => 'JB 1', 'scorer' => '']], 'hall_duties' => [],
        ]);

        $this->assertSame(1, $validation['count']);
        $this->assertContains('B- en C-jeugd mogen niet fluiten.', $validation['matches'][$match->id]['referee']);
    }

    /** A team with a points gap would otherwise get eight duties on one Saturday. */
    public function test_a_team_gets_no_more_duties_per_day_than_the_limit(): void
    {
        $this->team('DS 5', ['volunteer_points_earned' => 0, 'volunteer_points_used' => 100]);
        $this->team('DS 6', ['volunteer_points_earned' => 90, 'volunteer_points_used' => 100]);
        $this->team('DS 7', ['volunteer_points_earned' => 95, 'volunteer_points_used' => 100]);
        foreach (['09:00', '11:00', '13:00', '15:00', '17:00'] as $number => $time) {
            $this->homeMatch(['time' => $time, 'match_code' => 'D'.$number]);
        }

        $draft = $this->planner()->generate($this->version);

        $duties = [];
        foreach ($draft['matches'] as $row) {
            foreach ([$row['referee'], $row['scorer']] as $code) {
                if ($code) {
                    $duties[$code] = ($duties[$code] ?? 0) + 1;
                }
            }
        }
        foreach ($draft['hall_duties'] as $duty) {
            if ($duty['team_code']) {
                $duties[$duty['team_code']] = ($duties[$duty['team_code']] ?? 0) + 1;
            }
        }

        $this->assertNotSame([], $duties, 'The generator does hand out duties.');
        foreach ($duties as $code => $count) {
            $this->assertLessThanOrEqual(3, $count, "{$code} got {$count} duties on one day.");
        }
        $this->assertGreaterThan(1, count($duties), 'The limit spreads the day over more teams.');
    }

    public function test_the_day_limit_is_configurable_and_zero_means_no_limit(): void
    {
        PlannerSetting::current()->update(['duty_rules' => ['max_duties_per_day' => 0]]);
        $this->team('DS 5', ['volunteer_points_earned' => 0, 'volunteer_points_used' => 100]);
        $this->team('DS 6', ['volunteer_points_earned' => 90, 'volunteer_points_used' => 100]);
        foreach (['09:00', '11:00', '13:00', '15:00'] as $number => $time) {
            $this->homeMatch(['time' => $time, 'match_code' => 'G'.$number]);
        }

        $draft = $this->planner()->generate($this->version);

        $ds5 = 0;
        foreach ($draft['matches'] as $row) {
            $ds5 += (int) ($row['referee'] === 'DS 5') + (int) ($row['scorer'] === 'DS 5');
        }
        $this->assertGreaterThan(3, $ds5, 'Without a limit the same team may keep filling the day.');
    }

    public function test_the_generator_does_not_just_pick_the_playing_team_as_scorer(): void
    {
        $this->team('MB 4', ['name' => 'AETOS MB 4']);
        $this->team('HS 4', ['name' => 'AETOS HS 4']);
        $this->homeMatch(['home_team_name' => 'AETOS MB 4']);

        $this->assertSame('HS 4', $this->planner()->generate($this->version)['matches'][0]['scorer']);
    }

    public function test_a_team_scoring_its_own_match_gives_a_valid_draft(): void
    {
        $mb4 = $this->team('MB 4', ['name' => 'AETOS MB 4', 'scores_own_match' => true]);
        $this->team('HS 4', ['name' => 'AETOS HS 4']);
        $this->homeMatch(['home_team_name' => 'AETOS MB 4', 'home_team_id' => $mb4->id]);

        $draft = $this->planner()->generate($this->version);
        $validation = $this->planner()->validate($this->version, $draft);

        $this->assertSame('MB 4', $draft['matches'][0]['scorer']);
        $this->assertSame(0, $validation['count']);
    }

    /** A hall duty without a team is not filled in yet, not filled in wrongly. */
    public function test_an_empty_hall_duty_is_no_issue(): void
    {
        $validation = $this->planner()->validate($this->version, ['matches' => [], 'hall_duties' => [[
            'date' => $this->saturday, 'start' => '09:00', 'end' => '12:00', 'venue_id' => $this->venue->id, 'team_code' => '',
        ]]]);

        $this->assertSame(0, $validation['count']);
        $this->assertSame([], $validation['hall_duties']);
    }

    public function test_an_unknown_team_at_a_hall_duty_is_still_reported(): void
    {
        $validation = $this->planner()->validate($this->version, ['matches' => [], 'hall_duties' => [[
            'date' => $this->saturday, 'start' => '09:00', 'end' => '12:00', 'venue_id' => $this->venue->id, 'team_code' => 'ZZ 9',
        ]]]);

        $this->assertSame(1, $validation['count']);
        $this->assertContains('Het ingevulde team is niet gevonden.', $validation['hall_duties'][0]);
    }

    public function test_validation_for_one_block_ignores_other_days(): void
    {
        $this->team('HS 4');
        $target = $this->homeMatch(['date' => $this->saturdayInWeeks(5)]);
        $otherDay = $this->homeMatch(['date' => $this->saturdayInWeeks(6)]);

        $validation = $this->planner()->validate($this->version, ['matches' => [
            ['id' => $target->id, 'referee' => 'HS 4', 'scorer' => ''],
            ['id' => $otherDay->id, 'referee' => '', 'scorer' => 'UNKNOWN TEAM'],
        ], 'hall_duties' => []], $this->saturdayInWeeks(5), $this->venue->id, true);

        $this->assertSame([], $validation['matches']);
        $this->assertSame(0, $validation['count']);
    }

    public function test_tallies_count_duties_without_the_volunteer_points_module(): void
    {
        $this->setUpDutyClub(features: ['duties']);
        PlannerSetting::current()->update(['points_referee_duty' => 5]);
        $team = $this->team('HS 4', ['volunteer_points_earned' => 50, 'volunteer_points_used' => 100]);
        $this->homeMatch();

        $tallies = $this->planner()->generate($this->version)['tallies'][$team->id];

        $this->assertSame(0, $tallies['used'], 'points standings are ignored without the module');
        $this->assertSame($tallies['referee'] + $tallies['scorer'] + $tallies['hall_duty'], $tallies['total']);
    }

    private function planner(): DutyPlanner
    {
        return app(DutyPlanner::class);
    }
}
