<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PlannerModelsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useClub($this->createClub());
    }

    public function test_venue_has_courts_relation(): void
    {
        $venue = Venue::factory()->create();
        Court::factory()->count(3)->create(['venue_id' => $venue->id]);

        $this->assertCount(3, $venue->courts);
    }

    public function test_version_has_matches_relation_and_status_enum(): void
    {
        $version = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->count(2)->create(['version_id' => $version->id]);

        $this->assertCount(2, $version->matches);
        $this->assertSame('draft', ScheduleVersionStatus::Draft->value);
        $this->assertSame('published', ScheduleVersionStatus::Published->value);
        $this->assertSame('archived', ScheduleVersionStatus::Archived->value);
        $this->assertSame(ScheduleVersionStatus::Draft, $version->status);
        $this->assertTrue($version->isDraft());
    }

    public function test_match_relations_and_constants(): void
    {
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $team = Team::factory()->create();
        $match = FixtureMatch::factory()->create([
            'venue_id' => $venue->id,
            'court_id' => $court->id,
            'home_team_id' => $team->id,
        ]);

        $this->assertTrue($match->venue->is($venue));
        $this->assertTrue($match->court->is($court));
        $this->assertTrue($match->homeTeam->is($team));
        $this->assertSame('home', MatchType::Home->value);
        $this->assertSame(MatchType::Home, $match->match_type);
        $this->assertContains(105, FixtureMatch::DURATION_OPTIONS);
    }

    public function test_the_club_scope_filters_per_club(): void
    {
        $other = $this->createClub();
        Team::factory()->create(['code' => 'HS1']);
        $this->useClub($other);
        Team::factory()->create(['code' => 'HS1']);

        $this->assertCount(1, Team::all());
        $this->assertSame(2, Team::withoutGlobalScope('club')->count());
    }

    public function test_availability_enums(): void
    {
        $this->assertSame('date', AvailabilityScope::Date->value);
        $this->assertSame('closed', AvailabilityStatus::Closed->value);
    }
}
