<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\ConflictEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ConflictEngineTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    private Court $court;

    private ScheduleVersion $version;

    private ConflictEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
        $this->version = ScheduleVersion::factory()->create();
        $this->engine = app(ConflictEngine::class);
    }

    public function test_date_rule_beats_weekday_rule_and_closed_venue_cascades(): void
    {
        Availability::create([
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'scope' => AvailabilityScope::Weekday, 'weekday' => 6, 'status' => AvailabilityStatus::Available,
        ]);
        $dateRule = Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-09-12',
            'status' => AvailabilityStatus::Closed,
        ]);

        $result = $this->engine->resolveAvailability(
            Availability::query()->get(), $this->venue->id, $this->court->id, '2026-09-12',
        );

        $this->assertSame(AvailabilityStatus::Closed, $result['status']);
        $this->assertTrue($result['rule']->is($dateRule));
    }

    public function test_a_closed_window_only_applies_within_that_window(): void
    {
        $this->dateRule(AvailabilityStatus::Closed, '18:00', '22:00');
        $rules = Availability::query()->get();

        $inside = $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '19:00', '20:45');
        $outside = $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '09:00', '10:45');

        $this->assertSame(AvailabilityStatus::Closed, $inside['status'], 'Within the window the venue is closed.');
        $this->assertSame(AvailabilityStatus::Available, $outside['status'], 'Outside it the rule does not apply.');
    }

    public function test_a_rental_window_excludes_everything_outside_it(): void
    {
        $this->dateRule(AvailabilityStatus::Available, '08:00', '22:30');
        $rules = Availability::query()->get();

        $inside = $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '09:00', '10:45');
        $before = $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '07:00', '08:45');
        $after = $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '22:00', '23:45');

        $this->assertSame(AvailabilityStatus::Available, $inside['status']);
        $this->assertSame(AvailabilityStatus::Closed, $before['status'], 'Before the rental the venue cannot be used.');
        $this->assertSame(AvailabilityStatus::Closed, $after['status'], 'A match running past the end does not count either.');
    }

    public function test_a_rule_without_times_still_applies_all_day(): void
    {
        $this->dateRule(AvailabilityStatus::Closed);
        $rules = Availability::query()->get();

        $this->assertSame(
            AvailabilityStatus::Closed,
            $this->engine->resolveAvailability($rules, $this->venue->id, $this->court->id, '2026-09-12', '09:00', '10:45')['status'],
        );
    }

    public function test_team_detection_is_word_bound_and_only_for_the_own_club(): void
    {
        $teams = collect([
            Team::factory()->make(['code' => 'HS1']),
            Team::factory()->make(['code' => 'HS10']),
        ]);

        $this->assertSame(['HS1'], $this->engine->detectTeams('AETOS HS1', $teams));
        $this->assertSame([], $this->engine->detectTeams('Andere club HS1', $teams));
        $this->assertSame(['HS10'], $this->engine->detectTeams('AETOS HS10', $teams));
    }

    /**
     * "MA+" is a team of its own, not an abbreviation of the MA line. Without
     * the plus in the comparison that team found itself in every "MA 4" and
     * those ghost conflicts blocked publishing the whole schedule.
     */
    public function test_team_code_with_plus_does_not_merge_with_the_numbered_teams(): void
    {
        $teams = collect([
            Team::factory()->make(['code' => 'MA 4']),
            Team::factory()->make(['code' => 'MA+']),
        ]);

        $this->assertSame(['MA 4'], $this->engine->detectTeams('AETOS MA 4', $teams));
        $this->assertSame(['MA+'], $this->engine->detectTeams('AETOS MA+', $teams));
    }

    /**
     * Team detection remembers its answer per name, because a version scan
     * asks the same thing thousands of times. Another team list must give a
     * new answer and not the remembered one of the previous list.
     */
    public function test_team_detection_remembers_per_team_list_and_not_beyond(): void
    {
        $first = collect([Team::factory()->make(['code' => 'HS 1'])]);
        $second = collect([Team::factory()->make(['code' => 'DS 1'])]);

        $this->assertSame(['HS 1'], $this->engine->detectTeams('AETOS HS 1', $first));
        $this->assertSame([], $this->engine->detectTeams('AETOS HS 1', $second));
        $this->assertSame(['HS 1'], $this->engine->detectTeams('AETOS HS 1', $first));
    }

    public function test_without_team_name_patterns_no_team_is_detected(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => []]);

        $this->assertSame([], $this->engine->detectTeams('AETOS HS1', collect([Team::factory()->make(['code' => 'HS1'])])));
    }

    /**
     * A version scan must say per finding which match causes it; otherwise a
     * conflict overview reads "the court is closed" without anyone knowing
     * where. Two matches with the same text therefore stay two rows.
     */
    public function test_version_scan_names_the_match_for_every_finding(): void
    {
        Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Weekday, 'weekday' => 6,
            'status' => AvailabilityStatus::Closed,
        ]);
        $first = $this->match(['date' => '2026-09-12', 'time' => '10:00']);
        $second = $this->match(['date' => '2026-09-19', 'time' => '10:00']);

        $closed = collect($this->engine->scanVersion($this->version))
            ->where('type', ConflictEngine::TYPE_AVAILABILITY);

        $this->assertSame(
            [$first->id, $second->id],
            $closed->pluck('subject_match_id')->sort()->values()->all(),
        );
    }

    public function test_placement_detects_court_overlap_and_team_conflict(): void
    {
        $team = Team::factory()->create(['code' => 'HS1']);
        $other = $this->match([
            'date' => '2026-09-12', 'time' => '10:00',
            'home_team_name' => 'AETOS HS1', 'home_team_id' => $team->id,
        ]);
        $match = $this->match(['date' => null, 'time' => null, 'home_team_name' => 'AETOS HS1']);

        $findings = $this->engine->checkPlacement($match, [
            'date' => '2026-09-12', 'time' => '10:30', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $this->assertContains(ConflictEngine::TYPE_COURT_OVERLAP, array_column($findings, 'type'));
        $this->assertContains(ConflictEngine::TYPE_TEAM, array_column($findings, 'type'));
        $this->assertContains($other->id, array_column($findings, 'match_id'));
        $this->assertSame(ConflictEngine::LEVEL_WARNING, collect($findings)->firstWhere('type', ConflictEngine::TYPE_TEAM)['level']);
    }

    public function test_same_team_on_the_same_day_without_overlap_is_a_warning(): void
    {
        Team::factory()->create(['code' => 'HS1']);
        $this->match(['date' => '2026-09-12', 'time' => '10:00', 'home_team_name' => 'AETOS HS1']);
        $match = $this->match(['date' => null, 'time' => null, 'home_team_name' => 'AETOS HS1']);

        $findings = $this->engine->checkPlacement($match, [
            'date' => '2026-09-12', 'time' => '18:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $teamFinding = collect($findings)->firstWhere('type', ConflictEngine::TYPE_TEAM);
        $this->assertSame(ConflictEngine::LEVEL_WARNING, $teamFinding['level']);
    }

    public function test_duplicate_row_with_the_same_teams_is_no_conflict(): void
    {
        $team = Team::factory()->create(['code' => 'JB3']);
        $this->match([
            'match_code' => 'JB3-ABC', 'date' => '2026-09-12', 'time' => '09:00',
            'home_team_name' => 'AETOS JB3', 'away_team_name' => 'LIVO JB 1', 'home_team_id' => $team->id,
        ]);
        $match = $this->match([
            'match_code' => 'ANDERE-CODE', 'date' => '2026-09-12', 'time' => '10:45',
            'home_team_name' => 'AETOS JB3', 'away_team_name' => 'LIVO JB 1', 'home_team_id' => $team->id,
        ]);

        $findings = $this->engine->checkPlacement($match, [
            'date' => '2026-09-12', 'time' => '09:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $this->assertSame([], $findings);
    }

    public function test_officials_conflict_within_two_hours_except_nevobo(): void
    {
        $this->match(['date' => '2026-09-12', 'time' => '10:00', 'referee' => 'Jan']);
        $match = $this->match(['date' => '2026-09-12', 'time' => '11:00']);

        $this->assertTrue($this->engine->officialsConflict($match, 'Jan', '2026-09-12', '11:00', $this->venue->id));
        $this->assertFalse($this->engine->officialsConflict($match, 'Jan', '2026-09-12', '12:30', $this->venue->id));
        $this->assertFalse($this->engine->officialsConflict($match, 'Nevobo', '2026-09-12', '11:00', $this->venue->id));
    }

    private function dateRule(AvailabilityStatus $status, ?string $start = null, ?string $end = null): Availability
    {
        return Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-09-12',
            'start_time' => $start, 'end_time' => $end, 'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function match(array $attributes): FixtureMatch
    {
        return FixtureMatch::factory()->create($attributes + [
            'version_id' => $this->version->id, 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'second_court_id' => null,
        ]);
    }
}
