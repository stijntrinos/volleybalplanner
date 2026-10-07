<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\ProposalFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ProposalFinderTest extends TestCase
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

    public function test_the_configured_team_time_on_the_same_day_comes_first(): void
    {
        $team = Team::factory()->create(['code' => 'HS 2', 'default_time' => '17:00']);
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'home_team_id' => $team->id,
            'date' => now()->addWeeks(2)->toDateString(), 'time' => '19:30',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $proposals = app(ProposalFinder::class)->find($match, $this->venue->id, $this->court->id, limit: 3);

        $this->assertSame($match->date->toDateString(), $proposals[0]['date']);
        $this->assertSame('17:00', $proposals[0]['time']);
        $this->assertSame(ProposalFinder::PHASE_TEAM_PREFERENCE, $proposals[0]['phase']);
    }

    public function test_alternative_times_come_up_over_several_match_days(): void
    {
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'date' => now()->addWeeks(2)->toDateString(), 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        // Occupy the original time slot on every candidate date. An
        // availability rule would close the whole day, and since every
        // candidate falls on the same weekday nothing would be left.
        foreach (range(-12, 12) as $offset) {
            FixtureMatch::factory()->create([
                'version_id' => $this->version->id,
                // Short block: the original slot is taken, the rest of the day is not.
                'date' => now()->addWeeks(2 + $offset)->toDateString(), 'time' => '10:00', 'duration_minutes' => 30,
                'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'match_code' => "TAKEN{$offset}",
                'home_team_name' => 'Orion HS 9', 'away_team_name' => 'Sliedrecht HS 9',
            ]);
        }

        $proposals = app(ProposalFinder::class)->find($match, $this->venue->id, $this->court->id, limit: 5);

        $this->assertNotEmpty($proposals, 'At least one alternative slot should be found');
        $this->assertNotContains('10:00', collect($proposals)->pluck('time')->all());
        $this->assertGreaterThan(
            1,
            collect($proposals)->pluck('date')->unique()->count(),
            'The finder must not spend its budget on one match day',
        );
    }

    public function test_slots_already_reviewed_cost_no_search_space(): void
    {
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'date' => now()->addWeeks(2)->toDateString(), 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $first = app(ProposalFinder::class)->find($match, $this->venue->id, $this->court->id, limit: 3);
        $skip = collect($first)->map(fn (array $p): string => $p['date'].' '.$p['time'])->all();
        $second = app(ProposalFinder::class)->find($match, $this->venue->id, $this->court->id, $skip, limit: 3);

        $this->assertCount(3, $first);
        $this->assertCount(3, $second, 'After skipping the finder should simply find three proposals again');
        $this->assertEmpty(
            array_intersect($skip, collect($second)->map(fn (array $p): string => $p['date'].' '.$p['time'])->all()),
        );
    }
}
