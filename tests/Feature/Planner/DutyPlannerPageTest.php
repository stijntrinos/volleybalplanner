<?php

namespace Tests\Feature\Planner;

use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutyPlannerPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_the_summary_counts_every_filled_in_duty(): void
    {
        $page = new DutyPlanner;
        $page->draft = [
            'matches' => [
                ['referee' => 'DS 1', 'scorer' => 'HS 2'],
                ['referee' => 'Nevobo', 'scorer' => null],
            ],
            'hall_duties' => [
                ['team_code' => 'DS 3'],
                ['team_code' => ''],
            ],
        ];
        $page->validation = ['count' => 2];

        $this->assertSame([
            'matches' => 2,
            'referees' => 2,
            'scorers' => 1,
            'hall_duties' => 1,
            'issues' => 2,
        ], $page->getSummaryProperty());
    }

    public function test_changing_the_version_invalidates_an_old_draft(): void
    {
        $page = new DutyPlanner;
        $page->draft = ['matches' => [['id' => 1]]];
        $page->validation = ['count' => 0];
        $page->checkedAt = '06-08-2026 10:00';

        $page->updatedVersionId();

        $this->assertSame([], $page->draft);
        $this->assertSame([], $page->validation);
        $this->assertNull($page->checkedAt);
    }

    public function test_team_options_only_hold_duty_teams_of_the_own_club(): void
    {
        $version = ScheduleVersion::factory()->create();
        Team::factory()->create(['code' => 'HS 1', 'include_in_duties' => true]);
        Team::factory()->create(['code' => 'DS 2', 'include_in_duties' => true]);
        Team::factory()->create(['code' => 'MC 9', 'include_in_duties' => false]);
        app(CurrentClub::class)->run($this->createClub(), fn () => Team::factory()->create(['code' => 'XX 1', 'include_in_duties' => true]));

        $page = new DutyPlanner;
        $page->versionId = $version->id;

        $this->assertSame(['DS 2' => 'DS 2', 'HS 1' => 'HS 1'], $page->getTeamOptionsProperty());
    }

    public function test_without_a_version_there_are_no_team_options(): void
    {
        $page = new DutyPlanner;
        $page->versionId = null;

        $this->assertSame([], $page->getTeamOptionsProperty());
    }
}
