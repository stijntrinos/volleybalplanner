<?php

namespace Tests\Feature\Planner\Security;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * A move comes from the browser (drag and drop, the planning assistant): the
 * venue and courts in it must belong to the club, and to each other.
 */
class PlacementValidationTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private FixtureMatch $match;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsClubUser(ClubRole::PlanningCommittee);

        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'date' => '2026-10-10', 'time' => '10:00',
        ]);
    }

    public function test_a_venue_of_another_club_is_refused(): void
    {
        [$foreignVenue, $foreignCourt] = app(CurrentClub::class)->run($this->createClub(), function (): array {
            $venue = Venue::factory()->create();

            return [$venue, Court::factory()->create(['venue_id' => $venue->id])];
        });

        $this->expectException(DomainException::class);
        app(PlannerService::class)->move($this->match, $this->placement($foreignVenue->id, $foreignCourt->id), confirmed: true);
    }

    public function test_a_court_of_another_venue_is_refused(): void
    {
        $otherCourt = Court::factory()->create(['venue_id' => Venue::factory()->create()->id]);

        $this->expectException(DomainException::class);
        app(PlannerService::class)->move($this->match, $this->placement($this->venue->id, $otherCourt->id), confirmed: true);
    }

    public function test_an_own_court_is_fine(): void
    {
        $result = app(PlannerService::class)->move($this->match, $this->placement($this->venue->id, $this->court->id, '11:00'), confirmed: true);

        $this->assertFalse($result['confirmation_required']);
        $this->assertSame('11:00', substr((string) $this->match->refresh()->time, 0, 5));
    }

    public function test_undo_does_not_touch_a_published_planning(): void
    {
        $result = app(PlannerService::class)->move($this->match, $this->placement($this->venue->id, $this->court->id, '11:00'), confirmed: true);
        $this->match->version->update(['status' => ScheduleVersionStatus::Published]);

        $this->assertFalse(app(PlannerService::class)->undo($result['undo_token']));
        $this->assertSame('11:00', substr((string) $this->match->refresh()->time, 0, 5));
    }

    /** @return array<string, mixed> */
    private function placement(int $venueId, int $courtId, string $time = '10:00'): array
    {
        return [
            'date' => '2026-10-10', 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $venueId, 'court_id' => $courtId, 'second_court_id' => null,
        ];
    }
}
