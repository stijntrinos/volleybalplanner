<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use App\Planner\Services\ScheduleVersionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The check and the write belong in the same transaction: otherwise two
 * planners can both get green on the same court and time slot.
 */
class PlannerLockingTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
    }

    public function test_moving_checks_and_writes_in_one_transaction(): void
    {
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        // The tests run on SQLite, which ignores row locking, so "FOR UPDATE"
        // never shows up. What holds everywhere and is what matters: the
        // check must fall inside the transaction that writes.
        $levels = [];
        DB::listen(function ($query) use (&$levels): void {
            $levels[] = [Str::lower($query->sql), DB::transactionLevel()];
        });

        app(PlannerService::class)->move($match, [
            'date' => '2026-09-19', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'second_court_id' => null,
        ]);

        $checkQueries = collect($levels)->filter(
            fn (array $line): bool => str_starts_with($line[0], 'select') && str_contains($line[0], 'planner_matches'),
        );

        $this->assertNotEmpty($checkQueries, 'The check should query the matches of that day');
        $this->assertTrue(
            $checkQueries->every(fn (array $line): bool => $line[1] > 0),
            'The check should run inside the transaction that writes the move',
        );
        $this->assertSame('2026-09-19', $match->refresh()->date->toDateString());
    }

    public function test_publishing_still_blocks_on_hard_conflicts(): void
    {
        FixtureMatch::factory()->count(2)->create([
            'version_id' => $this->version->id,
            'date' => '2026-09-19', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $this->expectException(DomainException::class);
        app(ScheduleVersionService::class)->publish($this->version);
    }

    public function test_publishing_without_conflicts_succeeds(): void
    {
        $this->assertTrue(app(ScheduleVersionService::class)->publish($this->version)->isPublished());
    }
}
