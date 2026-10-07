<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Services\DutyTallyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutyTallyServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    /** @param  list<string>|null  $features */
    private function club(?array $features = null): void
    {
        $this->useClub($this->createClub($features));
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
    }

    public function test_spellings_of_the_same_team_code_add_up(): void
    {
        $this->club();
        $team = Team::factory()->create(['code' => 'HS 1']);
        PlannerSetting::current()->update(['points_referee_duty' => 2, 'points_hall_duty' => 3]);

        // Exactly the variants that come from import, RSS and manual input.
        foreach (['HS 1', 'HS1', 'hs 1'] as $index => $variant) {
            $this->match(['referee' => $variant, 'match_code' => "ABC{$index}"]);
        }
        $this->hallDuty($team, '2026-09-19');

        $rows = $this->tally();

        $this->assertCount(1, $rows);
        $this->assertSame('HS 1', $rows[0]['team'], 'The official team code determines the display');
        $this->assertSame(6, $rows[0]['referee']);
        $this->assertSame(3, $rows[0]['hall_duty']);
        $this->assertSame(9, $rows[0]['total']);
    }

    public function test_with_a_period_only_the_duties_within_count(): void
    {
        $this->club();
        $team = Team::factory()->create(['code' => 'HS 1']);
        PlannerSetting::current()->update(['points_referee_duty' => 2, 'points_hall_duty' => 3]);
        foreach (['2026-09-12', '2026-10-03', '2026-11-14'] as $index => $date) {
            $this->match(['date' => $date, 'referee' => 'HS 1', 'match_code' => "P{$index}"]);
        }
        foreach (['2026-10-03', '2026-11-14'] as $date) {
            $this->hallDuty($team, $date);
        }

        $rows = $this->tally('2026-10-01', '2026-10-31');
        $this->assertSame(2, $rows[0]['referee']);
        $this->assertSame(3, $rows[0]['hall_duty']);

        $this->assertSame(4, $this->tally(null, '2026-10-03')[0]['referee'], 'The end date itself counts.');
        $this->assertSame(6, $this->tally()[0]['referee']);
    }

    public function test_nevobo_and_empty_duties_do_not_count(): void
    {
        $this->club();
        $this->match(['referee' => 'Nevobo', 'scorer' => '']);

        $this->assertSame([], $this->tally());
    }

    public function test_points_standings_and_filter_are_taken_into_account(): void
    {
        $this->club();
        $visible = Team::factory()->create([
            'code' => 'DS 1', 'include_volunteers' => true, 'volunteer_points_used' => 100, 'volunteer_points_earned' => 25,
        ]);
        Team::factory()->create(['code' => 'DS 2', 'include_volunteers' => false]);
        PlannerSetting::current()->update(['points_scorer_duty' => 10]);
        $this->match(['scorer' => $visible->code]);

        $rows = $this->tally();

        $this->assertCount(1, $rows);
        $this->assertSame(100, $rows[0]['used']);
        $this->assertSame(25, $rows[0]['earned']);
        $this->assertSame(10, $rows[0]['scorer']);
        $this->assertSame(65.0, $rows[0]['percentage']);
    }

    public function test_volleystars_tasks_count_with_the_module(): void
    {
        $this->club();
        $team = Team::factory()->create(['code' => 'DS 1']);
        PlannerSetting::current()->update(['points_volleystars' => 4]);
        $this->volleystarsTask($team);

        $rows = $this->tally();

        $this->assertSame(4, $rows[0]['volleystars']);
        $this->assertSame(4, $rows[0]['total']);
    }

    public function test_without_volunteer_points_duties_are_counted_and_point_columns_left_out(): void
    {
        $this->club(['duties', 'volleystars']);
        $team = Team::factory()->create(['code' => 'HS 1', 'volunteer_points_used' => 100, 'volunteer_points_earned' => 25]);
        PlannerSetting::current()->update(['points_referee_duty' => 2, 'points_hall_duty' => 3]);
        $this->match(['referee' => 'HS 1', 'match_code' => 'A1']);
        $this->match(['scorer' => 'HS 1', 'match_code' => 'A2']);
        $this->hallDuty($team, '2026-09-19');

        $row = $this->tally()[0];

        $this->assertSame(
            ['team' => 'HS 1', 'referee' => 1, 'scorer' => 1, 'hall_duty' => 1, 'volleystars' => 0, 'total' => 3],
            $row,
        );
    }

    public function test_without_the_volleystars_module_volleystars_is_left_out(): void
    {
        $this->club(['duties', 'volunteer_points']);
        $team = Team::factory()->create(['code' => 'DS 1']);
        PlannerSetting::current()->update(['points_volleystars' => 4]);
        $this->volleystarsTask($team);

        $row = $this->tally()[0];

        $this->assertArrayNotHasKey('volleystars', $row);
        $this->assertSame(0, $row['total']);
        $this->assertArrayHasKey('percentage', $row);
    }

    private function tally(?string $from = null, ?string $to = null): array
    {
        return app(DutyTallyService::class)->perTeam($this->version, $from, $to);
    }

    private function match(array $attributes): FixtureMatch
    {
        return FixtureMatch::factory()->create($attributes + [
            'version_id' => $this->version->id, 'date' => '2026-09-19', 'referee' => null, 'scorer' => null,
        ]);
    }

    private function hallDuty(Team $team, string $date): void
    {
        HallDuty::create([
            'version_id' => $this->version->id, 'date' => $date, 'start_time' => '09:00', 'end_time' => '17:00',
            'team_id' => $team->id, 'venue_id' => $this->venue->id,
        ]);
    }

    private function volleystarsTask(Team $team): void
    {
        $block = VolleystarsBlock::create(['version_id' => $this->version->id, 'date' => '2026-09-19', 'venue_id' => $this->venue->id]);
        $block->tasks()->create(['role' => VolleystarsRole::Setup, 'team_id' => $team->id, 'sort_order' => 0]);
    }
}
