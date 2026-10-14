<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
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

    public function test_a_youth_match_stays_in_its_half_of_the_season(): void
    {
        $this->competitionRules(['youth_spring_start' => '01-01', 'youth_spring_end' => '04-30']);
        $year = now()->year + 1;
        $autumn = $this->proposalsFor('JA 1', 'DYNAMO JA 1', 'Orion JA 1', "{$year}-12-05");
        $spring = $this->proposalsFor('MB 2', 'DYNAMO MB 2', 'Orion MB 1', "{$year}-01-09");

        $this->assertNotEmpty($autumn);
        $this->assertTrue(collect($autumn)->every(fn (array $p): bool => $p['date'] <= "{$year}-12-31"));
        $this->assertNotEmpty($spring);
        $this->assertTrue(collect($spring)->every(fn (array $p): bool => $p['date'] >= "{$year}-01-01" && $p['date'] < "{$year}-05-01"));
    }

    public function test_an_internal_senior_match_lies_before_the_deadline(): void
    {
        $this->competitionRules(['internal_senior_deadline' => '03-01']);
        $year = now()->year + 1;
        $internal = $this->proposalsFor('HS 2', 'DYNAMO HS 2', 'DYNAMO HS 3', "{$year}-02-13", awayCode: 'HS 3');
        $regular = $this->proposalsFor('HS 2', 'DYNAMO HS 2', 'Orion HS 1', "{$year}-02-13");

        $this->assertNotEmpty($internal);
        $this->assertTrue(collect($internal)->every(fn (array $p): bool => $p['date'] < "{$year}-03-01"));
        $this->assertTrue(collect($regular)->contains(fn (array $p): bool => $p['date'] >= "{$year}-03-01"));
    }

    public function test_without_rules_youth_and_internal_matches_are_not_limited(): void
    {
        $this->competitionRules([]);
        $year = now()->year + 1;

        $this->assertTrue(collect($this->proposalsFor('JA 1', 'DYNAMO JA 1', 'Orion JA 1', "{$year}-12-05"))
            ->contains(fn (array $p): bool => $p['date'] >= ($year + 1).'-01-01'));
        $this->assertTrue(collect($this->proposalsFor('HS 2', 'DYNAMO HS 2', 'DYNAMO HS 3', "{$year}-02-13", awayCode: 'HS 3'))
            ->contains(fn (array $p): bool => $p['date'] >= "{$year}-03-01"));
    }

    /** @param array<string, string> $rules */
    private function competitionRules(array $rules): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['DYNAMO'], ...$rules]);
    }

    /** @return list<array<string, mixed>> */
    private function proposalsFor(string $code, string $home, string $away, string $date, ?string $awayCode = null): array
    {
        $team = Team::query()->firstWhere('code', $code) ?? Team::factory()->create(['code' => $code]);
        $awayTeam = $awayCode ? (Team::query()->firstWhere('code', $awayCode) ?? Team::factory()->create(['code' => $awayCode])) : null;
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'home_team_id' => $team->id, 'away_team_id' => $awayTeam?->id,
            'home_team_name' => $home, 'away_team_name' => $away, 'date' => $date, 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        return app(ProposalFinder::class)->find($match, $this->venue->id, $this->court->id, limit: 60);
    }
}
