<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use App\Planner\Services\AwayMatchService;
use App\Planner\Services\ConflictEngine;
use App\Planner\Services\ProposalFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * A version scan runs over a whole season. Whatever is fetched again per match
 * adds up to hundreds of queries there.
 */
class ConflictEngineQueriesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_a_version_scan_fetches_availability_and_teams_only_once(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        Team::factory()->create(['code' => 'HS1']);
        foreach (['10:00', '12:00', '14:00', '16:00', '18:00'] as $time) {
            FixtureMatch::factory()->create([
                'version_id' => $version->id, 'date' => '2026-09-12',
                'time' => $time, 'venue_id' => $venue->id, 'court_id' => $court->id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ConflictEngine::class)->scanVersion($version);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(1, $queries->filter(fn (string $q): bool => str_contains($q, 'planner_availabilities'))->count());
        $this->assertSame(1, $queries->filter(fn (string $q): bool => str_contains($q, 'planner_teams'))->count());
    }

    /**
     * Only single checks requested by a person go into the audit log; checks
     * with logging switched off (as the proposal finder does) do not.
     */
    public function test_only_logged_checks_fill_the_audit_log(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => now()->addWeek()->toDateString(),
            'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);
        $placement = [
            'date' => $match->date->toDateString(), 'time' => '10:00',
            'duration_minutes' => $match->duration_minutes, 'venue_id' => $venue->id, 'court_id' => $court->id,
        ];

        app(AvailabilityChecker::class)->check($match, $placement, log: false);
        $this->assertSame(0, ActivityLog::query()->where('log_name', 'planner-availability-check')->count());

        app(AvailabilityChecker::class)->check($match, $placement);
        $this->assertSame(1, ActivityLog::query()->where('log_name', 'planner-availability-check')->count());
    }

    /**
     * The away list asks for conflicts per row; with the version's schedule
     * preloaded that may not cost a query per row.
     */
    public function test_the_away_list_fetches_the_schedule_once(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $version = ScheduleVersion::factory()->create();
        Team::factory()->create(['code' => 'DS 1', 'name' => 'AETOS DS 1']);
        $away = collect(['2026-09-12', '2026-09-19', '2026-09-26'])->map(fn (string $date, int $index): FixtureMatch => FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_type' => MatchType::Away,
            'match_code' => 'AWAY-'.$index, 'date' => $date, 'time' => '19:00', 'duration_minutes' => 105,
            'home_team_name' => 'Orion DS 2', 'away_team_name' => 'AETOS DS 1',
        ]));
        $schedule = FixtureMatch::query()->where('version_id', $version->id)->get();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service = app(AwayMatchService::class);
        $away->each(fn (FixtureMatch $match) => $service->conflicts($match, $schedule));
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(0, $queries->filter(fn (string $q): bool => str_contains($q, 'planner_matches'))->count());
        $this->assertSame(1, $queries->filter(fn (string $q): bool => str_contains($q, 'planner_teams'))->count());
    }

    public function test_the_proposal_finder_does_not_fill_the_audit_log(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => now()->addWeek()->toDateString(),
            'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);

        app(ProposalFinder::class)->find($match, $venue->id, $court->id, limit: 3);

        $this->assertSame(0, ActivityLog::query()->where('log_name', 'planner-availability-check')->count());
    }
}
