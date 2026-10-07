<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\Activity;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PlannerExtendedSchemaTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_remaining_planner_tables_exist(): void
    {
        foreach ([
            'planner_activities', 'planner_hall_duties',
            'planner_volleystars_blocks', 'planner_volleystars_tasks',
            'planner_nevobo_changes', 'planner_team_sources',
            'planner_user_preferences', 'planner_settings',
            'planner_venue_aliases', 'planner_trainings', 'planner_change_requests',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} is missing");
        }
    }

    public function test_new_models_are_club_scoped_and_have_relations(): void
    {
        $club = $this->useClub($this->createClub());
        $venue = Venue::factory()->create();
        $team = Team::factory()->create();
        $version = ScheduleVersion::factory()->create();

        $activity = Activity::create([
            'version_id' => $version->id,
            'date' => '2026-09-12', 'start_time' => '10:00', 'title' => 'Training',
        ]);
        $block = VolleystarsBlock::create([
            'version_id' => $version->id,
            'date' => '2026-09-12', 'venue_id' => $venue->id,
        ]);
        $task = VolleystarsTask::create([
            'block_id' => $block->id,
            'role' => VolleystarsRole::Scoring, 'team_id' => $team->id,
        ]);
        $source = TeamSource::create([
            'team_id' => $team->id,
            'name' => 'Nevobo', 'url' => 'https://example.test/feed',
        ]);
        PlannerSetting::create();

        $this->assertTrue($activity->version->is($version));
        $this->assertTrue($task->block->is($block));
        $this->assertTrue($source->team->is($team));
        $this->assertSame($club->id, $source->club_id);
        $this->assertCount(1, PlannerSetting::all());
    }
}
