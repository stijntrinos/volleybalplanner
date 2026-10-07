<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Services\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The venue bar only showed venues where matches were played or trained.
 * When Volleystars was in another venue than the matches that day, that
 * block was nowhere to be seen. (Aetos DagplanningZalenbalkTest)
 */
class DayPlannerVenueBarTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $playVenue;

    private Venue $otherVenue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
        $this->playVenue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $this->otherVenue = Venue::factory()->create(['name' => 'Elderveld']);
        PlannerSetting::current()->update(['main_venue_id' => $this->playVenue->id]);

        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_type' => MatchType::Home,
            'date' => '2026-10-03', 'time' => '17:00', 'venue_id' => $this->playVenue->id,
        ]);
    }

    public function test_a_volleystars_block_in_another_venue_is_in_the_bar_and_open(): void
    {
        VolleystarsBlock::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-10-03', 'venue_id' => $this->otherVenue->id,
        ]);

        $day = app(PlannerService::class)->dayData($this->version, '2026-10-03');

        $this->assertContains($this->otherVenue->id, collect($day['venues'])->pluck('id')->all());
        $this->assertContains($this->otherVenue->id, $day['default_venues'], 'the venue should be open too');
        $this->assertContains($this->playVenue->id, $day['default_venues']);
    }

    public function test_a_hall_duty_in_another_venue_is_in_the_bar_too(): void
    {
        $team = Team::factory()->create(['code' => 'HS 1']);
        HallDuty::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-10-03', 'start_time' => '09:00', 'end_time' => '13:00',
            'venue_id' => $this->otherVenue->id, 'team_id' => $team->id,
        ]);

        $day = app(PlannerService::class)->dayData($this->version, '2026-10-03');

        $this->assertContains($this->otherVenue->id, collect($day['venues'])->pluck('id')->all());
        $this->assertContains($this->otherVenue->id, $day['default_venues']);
    }

    public function test_a_venue_without_anything_stays_out_of_the_bar(): void
    {
        $day = app(PlannerService::class)->dayData($this->version, '2026-10-03');

        $this->assertNotContains($this->otherVenue->id, collect($day['venues'])->pluck('id')->all());
        $this->assertSame([$this->playVenue->id], $day['default_venues']);
    }
}
