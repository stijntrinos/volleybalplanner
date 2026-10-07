<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Services\ConflictEngine;
use App\Planner\Services\ProvisionalMove;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Moving a match to another day is a proposal. Until it is agreed the match
 * stays on the old day, and the proposal marks the new day, where it keeps the
 * court occupied. The PlannerService flows (move, cancel, confirm, undo, day
 * data) are tested with PlannerService.
 */
class ProvisionalMoveTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court1;

    private Court $court2;

    private ProvisionalMove $move;

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
        $this->move = app(ProvisionalMove::class);
    }

    public function test_only_a_move_to_another_day_is_a_proposal(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $unscheduled = $this->match('W-2', null, null);

        $this->assertTrue($this->move->isProposal($match, $this->placement('2026-09-19', '12:00', $this->court2)));
        $this->assertFalse($this->move->isProposal($match, $this->placement('2026-09-12', '11:00', $this->court2)));
        $this->assertFalse($this->move->isProposal($unscheduled, $this->placement('2026-09-19', '12:00', $this->court1)));
    }

    public function test_columns_and_cleared_cover_every_provisional_column(): void
    {
        $columns = $this->move->columns($this->placement('2026-09-19', '12:00', $this->court2));

        $this->assertSame([
            'provisional_date' => '2026-09-19', 'provisional_time' => '12:00', 'provisional_duration_minutes' => 105,
            'provisional_venue_id' => $this->venue->id, 'provisional_court_id' => $this->court2->id,
            'provisional_second_court_id' => null,
        ], $columns);
        $this->assertSame(array_keys($columns), array_keys($this->move->cleared()));
        $this->assertSame([null], array_values(array_unique($this->move->cleared())));
    }

    public function test_a_proposal_keeps_its_court_occupied(): void
    {
        $proposed = $this->match('W-1', '2026-09-12', '10:00');
        $proposed->update($this->move->columns($this->placement('2026-09-19', '12:00', $this->court2)));
        $other = $this->match('W-2', null, null, ['home_team_name' => 'AETOS DS 2', 'away_team_name' => 'Orion DS 1']);

        $findings = app(ConflictEngine::class)->checkPlacement($other, $this->placement('2026-09-19', '12:30', $this->court2));

        $this->assertContains(ConflictEngine::TYPE_COURT_OVERLAP, array_column($findings, 'type'));
    }

    public function test_on_date_returns_unsaved_copies_at_the_provisional_place(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $match->update($this->move->columns($this->placement('2026-09-19', '12:00', $this->court2)));

        $copies = $this->move->onDate($this->version->id, '2026-09-19');

        $this->assertCount(1, $copies);
        $this->assertSame('2026-09-19', $copies[0]->date->toDateString());
        $this->assertSame($this->court2->id, $copies[0]->court_id);
        $this->assertSame('2026-09-12', $match->refresh()->date->toDateString());
    }

    public function test_finalize_makes_the_proposal_the_planning_and_moves_the_baseline(): void
    {
        $match = $this->match('W-1', '2026-09-12', '10:00');
        $match->update($this->move->columns($this->placement('2026-09-19', '12:00', $this->court2)));

        $match->update($this->move->finalize($match->refresh()));

        $match->refresh();
        $this->assertSame('2026-09-19', $match->date->toDateString());
        $this->assertSame('12:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($this->court2->id, $match->court_id);
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertSame('Middachtensingel', $match->original_venue);
        $this->assertSame('2', $match->original_court);
        $this->assertNull($match->provisional_date);
    }

    public function test_an_old_request_is_converted_into_a_proposal(): void
    {
        // This is how a request looked before this way of working: already at the new place.
        $match = $this->match('W-1', '2026-09-19', '12:00', [
            'court_id' => $this->court2->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00',
            'original_venue' => 'Middachtensingel, Middachtensingel 4, 6825HN  Arnhem', 'original_court' => '1',
            'change_status' => ChangeStatus::Requested, 'change_requested' => true,
        ]);

        foreach ($this->move->toConvert() as $toConvert) {
            $this->move->convertToProposal($toConvert);
        }

        $match->refresh();
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $this->assertSame('10:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($this->venue->id, $match->venue_id);
        $this->assertSame($this->court1->id, $match->court_id);
        $this->assertSame('2026-09-19', $match->provisional_date->toDateString());
        $this->assertSame('12:00', FixtureMatch::shortTime($match->provisional_time));
        $this->assertSame($this->court2->id, $match->provisional_court_id);
        $this->assertSame(ChangeStatus::Requested, $match->change_status);
    }

    public function test_an_archived_version_is_not_converted(): void
    {
        $this->version->update(['status' => ScheduleVersionStatus::Archived]);
        $this->match('W-1', '2026-09-19', '12:00', [
            'original_date' => '2026-09-12', 'original_time' => '10:00',
            'change_status' => ChangeStatus::Requested, 'change_requested' => true,
        ]);

        $this->assertCount(0, $this->move->toConvert());
    }

    public function test_venue_for_name_finds_spelling_variants_and_aliases(): void
    {
        VenueAlias::factory()->create(['venue_id' => $this->venue->id, 'name' => 'MDS']);

        $this->assertTrue($this->venue->is($this->move->venueForName('Midachtensingel, Middachtensingel 4')));
        $this->assertTrue($this->venue->is($this->move->venueForName('mds')));
        $this->assertNull($this->move->venueForName('De Laar'));
        $this->assertNull($this->move->venueForName(null));
    }

    /** @param array<string, mixed> $extra */
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
