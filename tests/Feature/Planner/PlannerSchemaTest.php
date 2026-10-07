<?php

namespace Tests\Feature\Planner;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlannerSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_planner_tables_exist(): void
    {
        foreach ([
            'planner_venues',
            'planner_courts',
            'planner_teams',
            'planner_schedule_versions',
            'planner_matches',
            'planner_availabilities',
            'planner_calendar_events',
            'planner_activity_colors',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table {$table} is missing");
        }
    }

    public function test_planner_matches_has_core_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('planner_matches', [
            'club_id', 'version_id', 'date', 'time', 'duration_minutes',
            'home_team_name', 'away_team_name', 'home_team_id', 'away_team_id',
            'match_code', 'match_type', 'venue_id', 'court_id', 'second_court_id',
            'original_date', 'original_time', 'original_venue', 'original_court',
            'change_status',
        ]));
    }

    public function test_website_columns_and_sponsors_are_not_ported(): void
    {
        $this->assertFalse(Schema::hasTable('planner_team_sponsor'));
        $this->assertFalse(Schema::hasColumn('planner_teams', 'slug'));
        $this->assertFalse(Schema::hasColumn('planner_settings', 'clubnaam'));
        $this->assertTrue(Schema::hasColumns('planner_settings', [
            'team_name_patterns', 'logo_path', 'primary_color', 'schedule_tagline',
        ]));
    }
}
