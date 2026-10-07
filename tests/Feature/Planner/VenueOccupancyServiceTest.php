<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Models\Availability;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\VenueOccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class VenueOccupancyServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    private Court $court;

    private ScheduleVersion $version;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->venue = Venue::factory()->create(['name' => 'De Laar']);
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);
        $this->version = ScheduleVersion::factory()->create();
    }

    public function test_a_match_day_within_the_rental_is_available(): void
    {
        $this->rental('2026-09-25', '18:30', '23:00');
        $this->match('2026-09-25', '19:00');
        $this->match('2026-09-25', '21:00');

        $row = $this->firstRow();

        $this->assertSame('2026-09-25', $row['date']);
        $this->assertSame('vr', $row['day']);
        $this->assertSame('18:30', $row['available_from']);
        $this->assertSame('23:00', $row['available_until']);
        $this->assertSame(2, $row['matches']);
        $this->assertSame('19:00', $row['first']);
        $this->assertSame('21:00', $row['last']);
        $this->assertSame('22:45', $row['end_of_last']);
        $this->assertSame(VenueOccupancyService::STATUS_AVAILABLE, $row['status']);
    }

    public function test_a_match_running_past_the_rental_is_a_conflict(): void
    {
        $this->rental('2026-09-25', '18:30', '22:00');
        $this->match('2026-09-25', '21:00');

        $this->assertSame(VenueOccupancyService::STATUS_CONFLICT, $this->firstRow()['status']);
    }

    public function test_a_match_starting_before_the_rental_is_a_conflict(): void
    {
        $this->rental('2026-09-25', '19:00', '23:00');
        $this->match('2026-09-25', '18:00');

        $this->assertSame(VenueOccupancyService::STATUS_CONFLICT, $this->firstRow()['status']);
    }

    public function test_a_day_without_recorded_availability_stands_out(): void
    {
        $this->match('2026-10-10', '09:00');

        $row = $this->firstRow();

        $this->assertSame(VenueOccupancyService::STATUS_UNKNOWN, $row['status']);
        $this->assertNull($row['available_from']);
    }

    public function test_a_closed_venue_without_matches_is_closed_and_with_matches_a_conflict(): void
    {
        Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-12-25',
            'status' => AvailabilityStatus::Closed, 'note' => 'Eerste kerstdag',
        ]);

        $row = $this->firstRow('2026-12-25', '2026-12-25');
        $this->assertSame(VenueOccupancyService::STATUS_CLOSED, $row['status']);
        $this->assertSame('Eerste kerstdag', $row['note']);

        $this->match('2026-12-25', '10:00');

        $this->assertSame(VenueOccupancyService::STATUS_CONFLICT, $this->firstRow('2026-12-25', '2026-12-25')['status']);
    }

    public function test_the_match_details_are_with_the_day(): void
    {
        $this->rental('2026-09-25', '18:30', '23:00');
        $this->match('2026-09-25', '19:00', 'XC401-AF', 'AETOS MC 4', 'Xanthos JC 1');

        $details = $this->firstRow()['details'];

        $this->assertCount(1, $details);
        $this->assertSame('XC401-AF', $details[0]['code']);
        $this->assertSame('AETOS MC 4', $details[0]['home']);
        $this->assertSame('Xanthos JC 1', $details[0]['away']);
        $this->assertSame('1', $details[0]['court']);
        $this->assertSame('19:00', $details[0]['start']);
        $this->assertSame('20:45', $details[0]['end']);
        $this->assertSame('105 min', $details[0]['duration']);
    }

    public function test_filtering_on_status_limits_the_result(): void
    {
        $this->rental('2026-09-25', '18:30', '23:00');
        $this->match('2026-09-25', '19:00');
        $this->match('2026-10-02', '09:00');

        $all = $this->overview();
        $onlyUnknown = $this->overview(['statuses' => [VenueOccupancyService::STATUS_UNKNOWN]]);

        $this->assertSame(2, $all['total']);
        $this->assertSame(1, $onlyUnknown['total']);
        $this->assertSame('2026-10-02', $onlyUnknown['venues'][0]['rows'][0]['date']);
    }

    public function test_match_days_only_leaves_out_empty_days(): void
    {
        Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-09-26',
            'status' => AvailabilityStatus::Closed,
        ]);
        $this->match('2026-09-25', '19:00');

        $this->assertSame(2, $this->overview()['total'], 'Without the filter you also see the closed day.');
        $this->assertSame(1, $this->overview(['matchDaysOnly' => true])['total']);
    }

    public function test_away_matches_do_not_count(): void
    {
        $this->rental('2026-09-25', '18:30', '23:00');
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->venue->id,
            'date' => '2026-09-25', 'time' => '19:00', 'duration_minutes' => 105, 'match_type' => MatchType::Away,
        ]);

        $this->assertSame(0, $this->firstRow()['matches']);
    }

    /** The main venue belongs at the top, also in the occupancy overview. */
    public function test_the_overview_starts_with_the_main_venue(): void
    {
        $main = Venue::factory()->create(['name' => 'Middachtensingel']);
        $second = Venue::factory()->create(['name' => 'Bakenhof']);
        PlannerSetting::current()->update(['main_venue_id' => $main->id]);
        foreach ([$second, $main] as $index => $venue) {
            FixtureMatch::factory()->create([
                'version_id' => $this->version->id, 'venue_id' => $venue->id,
                'date' => '2026-09-12', 'time' => '09:00', 'duration_minutes' => 105,
                'match_type' => MatchType::Home, 'match_code' => 'B'.$index,
            ]);
        }

        $overview = app(VenueOccupancyService::class)->overview($this->version, '2026-09-01', '2026-09-30');

        $this->assertSame(['Middachtensingel', 'Bakenhof'], array_column($overview['venues'], 'venue'));
    }

    private function rental(string $date, string $from, string $until): void
    {
        Availability::create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date, 'date' => $date,
            'start_time' => $from, 'end_time' => $until, 'status' => AvailabilityStatus::Available,
        ]);
    }

    private function match(string $date, string $time, ?string $code = null, string $home = 'AETOS DS 1', string $away = 'Trivos DS 1'): void
    {
        $code ??= 'W'.str_pad((string) ++$this->counter, 3, '0', STR_PAD_LEFT);

        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'match_code' => $code,
            'home_team_name' => $home, 'away_team_name' => $away,
        ]);
    }

    /** @param array<string, mixed> $options */
    private function overview(array $options = []): array
    {
        return app(VenueOccupancyService::class)->overview(
            $this->version,
            $options['from'] ?? '2026-09-01',
            $options['to'] ?? '2026-12-31',
            $options['venueId'] ?? null,
            $options['statuses'] ?? [],
            $options['matchDaysOnly'] ?? false,
        );
    }

    /** @return array<string, mixed> */
    private function firstRow(string $from = '2026-09-01', string $to = '2026-12-31'): array
    {
        return $this->overview(['from' => $from, 'to' => $to])['venues'][0]['rows'][0];
    }
}
