<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ChangeMarkerService;
use App\Planner\Services\PlannerService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Marking a home match as "has to move" without a new place yet. */
class ChangeMarkerServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
    }

    public function test_a_marked_match_stays_in_place_and_shows_up_to_plan(): void
    {
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00']);

        app(ChangeMarkerService::class)->mark($match);

        $match->refresh();
        $this->assertSame(ChangeStatus::ToChange, $match->change_status);
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $card = collect(app(PlannerService::class)->dayData($this->version, '2026-09-12')['to_plan'])->firstWhere('id', $match->id);
        $this->assertTrue($card['to_change']);

        app(ChangeMarkerService::class)->clear($match);
        $this->assertNull($match->refresh()->change_status);
    }

    public function test_a_running_request_cannot_be_marked(): void
    {
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'change_status' => ChangeStatus::Requested]);

        $this->expectException(DomainException::class);

        app(ChangeMarkerService::class)->mark($match);
    }

    public function test_only_home_matches_in_a_draft_can_be_marked(): void
    {
        $away = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'match_type' => MatchType::Away]);
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
        $home = FixtureMatch::factory()->create(['version_id' => $published->id]);

        foreach ([$away, $home] as $match) {
            try {
                app(ChangeMarkerService::class)->mark($match);
                $this->fail('Marking should have been refused.');
            } catch (DomainException) {
                $this->assertNull($match->refresh()->change_status);
            }
        }
    }
}
