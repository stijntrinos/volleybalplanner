<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ClubDeletionTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_a_club_with_planning_data_can_be_deleted(): void
    {
        $club = $this->useClub($this->createClub());
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $team = Team::factory()->create();
        FixtureMatch::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id]);
        // These reference venue and team with RESTRICT; that is where it used to break.
        HallDuty::factory()->create(['version_id' => $version->id, 'team_id' => $team->id, 'venue_id' => $venue->id]);

        $club->delete();

        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
        $this->assertDatabaseMissing('planner_hall_duties', ['club_id' => $club->id]);
        $this->assertDatabaseMissing('planner_venues', ['club_id' => $club->id]);
    }

    public function test_a_venue_in_use_stays_protected(): void
    {
        $this->useClub($this->createClub());
        $venue = Venue::factory()->create();
        HallDuty::factory()->create(['venue_id' => $venue->id]);

        $this->expectException(QueryException::class);
        $venue->delete();
    }

    public function test_the_same_source_cannot_be_attached_to_a_team_twice(): void
    {
        // The type belongs in the index: a team follows both the schedule and
        // the standings of the same pool, and both are called "Competitie".
        $this->assertTrue(
            collect(Schema::getIndexes('planner_team_sources'))
                ->contains(fn (array $index): bool => $index['columns'] === ['team_id', 'name', 'type'] && $index['unique']),
            'planner_team_sources is missing a unique index on (team_id, name, type)',
        );

        $this->useClub($this->createClub());
        $team = Team::factory()->create();
        TeamSource::create(['team_id' => $team->id, 'name' => 'Nevobo', 'url' => 'https://a.test/a.xml']);

        $this->expectException(QueryException::class);
        TeamSource::create(['team_id' => $team->id, 'name' => 'Nevobo', 'url' => 'https://b.test/b.xml']);
    }
}
