<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\NevoboChangeField;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\NevoboChange;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Services\NevoboChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class NevoboChangeServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_only_deviating_fields_are_shown_and_can_be_marked_processed(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create();
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id,
            'date' => '2026-09-19', 'time' => '12:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
            'original_date' => '2026-09-12', 'original_time' => '12:00',
            'original_venue' => 'De Laar', 'original_court' => '1',
        ]);

        $service = app(NevoboChangeService::class);
        $changes = $service->forVersion($version);
        $this->assertCount(1, $changes);
        $this->assertSame(['date'], array_column($changes[0]['fields'], 'field'));
        $this->assertSame('2026-09-12', $changes[0]['fields'][0]['old']);
        $this->assertSame('2026-09-19', $changes[0]['fields'][0]['new']);

        $service->mark($match, NevoboChangeField::Date, true);
        $this->assertTrue($service->forVersion($version)[0]['fields'][0]['processed']);
    }

    public function test_all_changes_of_a_match_are_processed_at_once(): void
    {
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-12', 'time' => '19:30',
            'nevobo_change_status' => NevoboChangeStatus::Conflict,
            'nevobo_new_date' => '2026-09-19', 'nevobo_new_time' => '20:00',
        ]);

        $count = app(NevoboChangeService::class)->markAll($match, true, null);

        $this->assertGreaterThan(0, $count, 'There should be at least one changed field.');
        $this->assertSame(
            $count,
            NevoboChange::query()->where('match_id', $match->id)->where('is_processed', true)->count(),
            'All changed fields should be processed in one go.',
        );
    }

    public function test_the_same_venue_under_another_nevobo_name_is_no_change(): void
    {
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        VenueAlias::create(['venue_id' => $venue->id, 'name' => 'Sporthal Middachtensingel']);
        $otherVenue = Venue::factory()->create(['name' => 'Valkenhuizen']);

        $base = [
            'version_id' => $version->id, 'date' => '2026-09-12', 'time' => '10:45',
            'original_date' => '2026-09-12', 'original_time' => '10:45',
            'court_id' => null, 'original_court' => null,
            'original_venue' => 'Sporthal Middachtensingel, Middachtensingel 4, 6825HN Arnhem',
        ];
        $same = FixtureMatch::factory()->create([...$base, 'venue_id' => $venue->id]);
        $moved = FixtureMatch::factory()->create([...$base, 'venue_id' => $otherVenue->id]);

        $changes = collect(app(NevoboChangeService::class)->forVersion($version));

        $this->assertNull($changes->firstWhere('match_id', $same->id), 'Only the spelling differs.');
        $this->assertSame(['venue'], array_column($changes->firstWhere('match_id', $moved->id)['fields'], 'field'));

        // The nightly sync uses the same rule: only a real move holds back a
        // new Nevobo schedule.
        $this->assertFalse($same->fresh()->deviatesFromNevobo());
        $this->assertTrue($moved->fresh()->deviatesFromNevobo());
    }
}
