<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use App\Planner\Services\ConflictEngine;
use App\Planner\Services\ProposalFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class AvailabilityCheckerTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
    }

    public function test_a_free_placement_is_green_and_audited(): void
    {
        [$match, $venue, $court] = $this->plannerData();

        $result = app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);

        $this->assertSame(AvailabilityChecker::RESULT_GREEN, $result['result']);
        $this->assertTrue($result['can_proceed']);
        $this->assertDatabaseHas('activity_log', ['subject_type' => $match->getMorphClass(), 'subject_id' => $match->id]);
    }

    public function test_external_source_error_gives_a_warning_with_configurable_override(): void
    {
        [$match, $venue, $court] = $this->plannerData();
        PlannerSetting::current()->update(['admin_override_external_error' => false]);

        $result = app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ], collect([['error' => true]]));

        $this->assertSame(AvailabilityChecker::RESULT_WARNING, $result['result']);
        $this->assertFalse($result['can_proceed']);

        PlannerSetting::current()->update(['admin_override_external_error' => true]);
        $this->assertTrue(app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ], collect([['error' => true]]))['can_proceed']);
    }

    public function test_own_match_is_not_reported_as_external_conflict_and_duplicates_are_merged(): void
    {
        [$match, $venue, $court] = $this->plannerData([
            'match_code' => 'JB3-ABC', 'home_team_name' => 'AETOS JB 3', 'away_team_name' => 'LIVO JB 1',
        ]);
        $team = Team::factory()->create();
        $match->update(['home_team_id' => $team->id]);
        $ownMatch = [
            'date' => '2026-09-12', 'team_id' => $team->id,
            'home_team' => 'AETOS JB 3', 'away_team' => 'LIVO JB 1',
        ];
        $otherMatch = [
            'date' => '2026-09-12', 'team_id' => $team->id,
            'home_team' => 'Andere club', 'away_team' => 'AETOS JB 3',
        ];

        $result = app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-09-12', 'time' => '09:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ], collect([$ownMatch, $ownMatch, $otherMatch, $otherMatch]));

        $externalConflicts = collect($result['reasons'])->where('type', ConflictEngine::TYPE_EXTERNAL_TEAM);

        $this->assertCount(1, $externalConflicts);
        $this->assertSame('Het team heeft op deze dag al een externe wedstrijd.', $externalConflicts->first()['message']);
        $this->assertSame(AvailabilityChecker::RESULT_RED, $result['result']);
    }

    public function test_changing_the_time_on_the_same_day_gives_no_external_day_conflict(): void
    {
        [$match, $venue, $court] = $this->plannerData(['date' => '2026-09-12', 'time' => '09:00']);
        $team = Team::factory()->create();
        $match->update(['home_team_id' => $team->id]);

        $result = app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-09-12', 'time' => '09:30', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ], collect([[
            'date' => '2026-09-12', 'team_id' => $team->id,
            'home_team' => 'Andere club', 'away_team' => 'AETOS',
        ]]));

        $this->assertFalse(collect($result['reasons'])->contains('type', ConflictEngine::TYPE_EXTERNAL_TEAM));
    }

    public function test_external_matches_come_from_the_active_sources_of_both_teams(): void
    {
        [$match] = $this->plannerData();
        $team = Team::factory()->create();
        $match->update(['home_team_id' => $team->id]);
        $source = TeamSource::factory()->create(['team_id' => $team->id, 'url' => 'https://example.test/feed.xml', 'is_active' => true]);
        TeamSource::factory()->create(['team_id' => $team->id, 'url' => 'https://example.test/inactive.xml', 'is_active' => false]);
        Http::fake(['example.test/feed.xml' => Http::response('<rss><channel><item><title>AETOS HS1 - Dynamo HS1</title><description>Datum 12-09-2026 10:30</description></item></channel></rss>')]);

        $external = app(AvailabilityChecker::class)->externalMatches($match);

        $this->assertCount(1, $external);
        $this->assertSame('2026-09-12', $external[0]['date']);
        $this->assertSame($team->id, $external[0]['team_id']);
        $this->assertSame($source->id, $external[0]['source_id']);
    }

    public function test_the_proposal_finder_gives_validated_slots_within_the_competition_period(): void
    {
        $this->travelTo('2026-08-01');
        [$match, $venue, $court] = $this->plannerData(['original_date' => '2026-09-12', 'original_time' => '12:00']);
        PlannerSetting::current()->update(['competition_start' => '2026-09-01', 'competition_end' => '2026-12-31']);

        $proposals = app(ProposalFinder::class)->find($match, $venue->id, $court->id, limit: 3);

        $this->assertCount(3, $proposals);
        $this->assertSame('12:00', $proposals[0]['time']);
        $this->assertSame(AvailabilityChecker::RESULT_GREEN, $proposals[0]['check']['result']);
    }

    /** @param array<string, mixed> $matchData */
    private function plannerData(array $matchData = []): array
    {
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $version = ScheduleVersion::factory()->create();
        $match = FixtureMatch::factory()->create($matchData + [
            'version_id' => $version->id, 'date' => null, 'time' => null,
        ]);

        return [$match, $venue, $court];
    }
}
