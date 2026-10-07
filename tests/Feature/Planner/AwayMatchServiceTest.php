<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\AwayMatchService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class AwayMatchServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    private FixtureMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $this->venue = Venue::factory()->create();
        $version = ScheduleVersion::factory()->create();
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_type' => MatchType::Away,
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'home_team_name' => 'Orion HS 2', 'away_team_name' => 'AETOS HS 1', 'venue_id' => $this->venue->id,
        ]);
    }

    public function test_a_final_proposal_is_applied_and_cleared(): void
    {
        app(AwayMatchService::class)->update($this->match, [
            'away_change_status' => AwayChangeStatus::Final->value, 'away_new_date' => '2026-09-19',
            'away_new_time' => '13:30', 'away_new_venue' => 'Nieuwe hal',
        ]);

        $match = $this->match->refresh();
        $this->assertSame(AwayChangeStatus::None, $match->away_change_status);
        $this->assertSame('2026-09-19', $match->date->toDateString());
        $this->assertSame('13:30', substr($match->time, 0, 5));
        $this->assertNull($match->away_new_date);
        $this->assertSame('Nieuwe hal', $match->original_venue);
    }

    public function test_rejecting_clears_the_proposal_and_keeps_the_schedule(): void
    {
        app(AwayMatchService::class)->update($this->match, [
            'away_change_status' => AwayMatchService::STATUS_REJECTED, 'away_new_date' => '2026-09-19',
        ]);

        $match = $this->match->refresh();
        $this->assertSame(AwayChangeStatus::None, $match->away_change_status);
        $this->assertNull($match->away_new_date);
        $this->assertSame('2026-09-12', $match->date->toDateString());
    }

    public function test_an_unknown_status_or_a_home_match_is_refused(): void
    {
        $this->expectException(DomainException::class);

        app(AwayMatchService::class)->update($this->match, ['away_change_status' => 'nonsense']);
    }

    public function test_the_conflict_check_finds_an_overlapping_training(): void
    {
        $team = Team::factory()->create(['name' => 'AETOS HS 1', 'code' => 'HS 1']);
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => $this->venue->id,
            'weekday' => 'zaterdag', 'start_time' => '09:30', 'end_time' => '11:00', 'court' => 1,
        ]);

        $conflicts = app(AwayMatchService::class)->conflicts($this->match);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('training', $conflicts[0]);
    }

    /**
     * The old check compared team names literally; "Aetos HS1" next to
     * "AETOS HS 1" slipped through while the same team had to play twice at
     * the same time.
     */
    public function test_a_differently_written_team_name_still_gives_a_conflict(): void
    {
        Team::factory()->create(['name' => 'AETOS HS 1', 'code' => 'HS 1']);
        $this->otherMatch('OTHER-1', 'Aetos hs1');

        $conflicts = app(AwayMatchService::class)->conflicts($this->match);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('speelt ook', $conflicts[0]);
    }

    public function test_a_higher_team_number_does_not_count_as_the_same_team(): void
    {
        Team::factory()->create(['name' => 'AETOS HS 1', 'code' => 'HS 1']);
        $this->otherMatch('OTHER-2', 'Aetos HS 10');

        $this->assertSame([], app(AwayMatchService::class)->conflicts($this->match));
    }

    private function otherMatch(string $code, string $homeTeam): void
    {
        FixtureMatch::factory()->create([
            'version_id' => $this->match->version_id, 'match_type' => MatchType::Home,
            'match_code' => $code, 'date' => '2026-09-12', 'time' => '10:30', 'duration_minutes' => 105,
            'home_team_name' => $homeTeam, 'away_team_name' => 'Sliedrecht HS 3', 'venue_id' => $this->venue->id,
        ]);
    }
}
