<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ConflictEngine;
use App\Planner\Services\PlannerService;
use App\Planner\Services\ScheduleVersionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Dragging a match to another day is a proposal. Until it is agreed the match
 * stays on the old day, also in what is published, and it marks the new day,
 * where it keeps the court occupied. These are the PlannerService flows; the
 * ProvisionalMove helpers are covered by ProvisionalMoveTest.
 */
class ProvisionalMovePlannerTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court1;

    private Court $court2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00'));
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);

        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel', 'for_matches' => true]);
        $this->court1 = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1', 'sort_order' => 1]);
        $this->court2 = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '2', 'sort_order' => 2]);
    }

    public function test_to_another_day_the_match_stays_with_a_proposal(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');

        $result = $this->service()->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $match->refresh();
        $this->assertTrue($result['provisional']);
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $this->assertSame('10:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($this->court1->id, $match->court_id);
        $this->assertSame('2026-09-19', $match->provisional_date->toDateString());
        $this->assertSame('12:00', FixtureMatch::shortTime($match->provisional_time));
        $this->assertSame($this->court2->id, $match->provisional_court_id);
        $this->assertSame(ChangeStatus::Requested, $match->change_status);
    }

    public function test_shifting_on_the_same_day_goes_directly_and_replaces_the_proposal(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $service = $this->service();
        $service->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $result = $service->move($match->refresh(), $this->placement('2026-09-12', '11:00', $this->court2));

        $match->refresh();
        $this->assertFalse($result['provisional']);
        $this->assertSame('11:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($this->court2->id, $match->court_id);
        $this->assertNull($match->provisional_date);
    }

    public function test_a_match_without_a_date_is_scheduled_directly(): void
    {
        $match = $this->match('W-1', null, null);

        $this->service()->move($match, $this->placement('2026-09-19', '12:00', $this->court1));

        $this->assertSame('2026-09-19', $match->refresh()->date->toDateString());
        $this->assertNull($match->provisional_date);
    }

    public function test_a_proposal_keeps_its_court_occupied(): void
    {
        $proposed = $this->match('W-1', '2026-09-12', '10:00');
        $this->service()->move($proposed, $this->placement('2026-09-19', '12:00', $this->court2));
        $other = $this->match('W-2', null, null, ['home_team_name' => 'AETOS DS 2', 'away_team_name' => 'Orion DS 1']);

        $findings = app(ConflictEngine::class)->checkPlacement($other, $this->placement('2026-09-19', '12:30', $this->court2));

        $this->assertContains(ConflictEngine::TYPE_COURT_OVERLAP, array_column($findings, 'type'));
    }

    public function test_rejecting_leaves_the_match_in_place_and_removes_the_proposal(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $service = $this->service();
        $service->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $service->cancelChangeRequest($match->refresh());

        $match->refresh();
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $this->assertSame($this->court1->id, $match->court_id);
        $this->assertNull($match->provisional_date);
        $this->assertNull($match->change_status);
        $this->assertFalse($match->change_requested);
    }

    public function test_confirming_puts_the_match_at_the_proposed_place(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $service = $this->service();
        $service->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $service->markNevoboApplied($match->refresh());

        $match->refresh();
        $this->assertSame('2026-09-19', $match->date->toDateString());
        $this->assertSame('12:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($this->court2->id, $match->court_id);
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertSame('2', $match->original_court);
        $this->assertNull($match->provisional_date);
        $this->assertSame(NevoboStatus::Applied, $match->nevobo_status);
    }

    public function test_undo_removes_the_proposal(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $service = $this->service();

        $result = $service->move($match, $this->placement('2026-09-19', '12:00', $this->court2));
        $this->assertTrue($service->undo($result['undo_token']));

        $match->refresh();
        $this->assertNull($match->provisional_date);
        $this->assertNull($match->change_status);
        $this->assertSame('2026-09-12', $match->date->toDateString());
    }

    public function test_the_planner_shows_the_proposal_on_the_new_day_and_the_match_on_the_old(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $service = $this->service();
        $service->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $oldDay = $service->dayData($this->version, '2026-09-12');
        $newDay = $service->dayData($this->version, '2026-09-19');

        $this->assertSame([$match->id], array_column($oldDay['matches'], 'id'));
        $this->assertStringContainsString('19-09-2026', $oldDay['matches'][0]['provisional']);
        $this->assertSame([], $newDay['matches']);
        $this->assertSame([$match->id], array_column($newDay['provisional'], 'id'));
        $this->assertSame($this->court2->id, $newDay['provisional'][0]['court_id']);
        $this->assertStringContainsString('12-09-2026', $newDay['provisional'][0]['currently_at']);
    }

    public function test_a_copy_of_the_version_takes_the_proposal_along(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $this->service()->move($match, $this->placement('2026-09-19', '12:00', $this->court2));

        $copy = app(ScheduleVersionService::class)->duplicate($this->version, 'Kopie');

        $this->assertSame('2026-09-19', $copy->matches()->first()->provisional_date->toDateString());
    }

    private function service(): PlannerService
    {
        return app(PlannerService::class);
    }

    /** @param  array<string, mixed>  $extra */
    private function match(string $code, ?string $date, ?string $time, array $extra = []): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => $code,
            'date' => $date, 'time' => $time, 'duration_minutes' => 105, 'match_type' => MatchType::Home,
            'venue_id' => $date ? $this->venue->id : null, 'court_id' => $date ? $this->court1->id : null, 'second_court_id' => null,
            'home_team_name' => 'AETOS JB 1', 'away_team_name' => 'SV Dynamo Apeldoorn JB 1',
            ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function placement(string $date, string $time, Court $court): array
    {
        return [
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $court->id, 'second_court_id' => null,
        ];
    }
}
