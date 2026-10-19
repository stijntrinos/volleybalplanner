<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Filament\Pages\VenueOccupancy;
use App\Planner\Models\Availability;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\VenueOccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class VenueOccupancyPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_period_starts_today_unless_the_season_is_over(): void
    {
        $this->actingAsClubUser();
        $this->travelTo('2026-10-19 12:00');
        $version = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2026-09-05', 'match_type' => MatchType::Home]);
        FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2027-04-24', 'match_type' => MatchType::Home]);

        $page = Livewire::test(VenueOccupancy::class)->set('versionId', $version->id);
        $this->assertSame(['19-10-2026', '24-04-2027'], [$page->get('fromDate'), $page->get('toDate')]);

        $this->travelTo('2027-06-01 12:00');
        $page = Livewire::test(VenueOccupancy::class)->set('versionId', $version->id);
        $this->assertSame(['05-09-2026', '24-04-2027'], [$page->get('fromDate'), $page->get('toDate')]);
    }

    public function test_a_quick_filter_sets_the_statuses_and_recognises_itself(): void
    {
        $page = new VenueOccupancy;

        $page->chooseFilter('conflicts');
        $this->assertSame([VenueOccupancyService::STATUS_CONFLICT], $page->statuses);
        $this->assertFalse($page->matchDaysOnly);
        $this->assertSame('conflicts', $page->activeFilter());

        $page->chooseFilter('match_days');
        $this->assertSame([], $page->statuses);
        $this->assertTrue($page->matchDaysOnly);
        $this->assertSame('match_days', $page->activeFilter());

        $page->chooseFilter('all');
        $this->assertSame('all', $page->activeFilter());
    }

    public function test_a_manual_selection_is_not_presented_as_quick_filter(): void
    {
        $page = new VenueOccupancy;
        $page->statuses = [VenueOccupancyService::STATUS_PARTIAL];

        $this->assertSame('custom', $page->activeFilter());
    }

    public function test_the_overview_follows_the_entered_period(): void
    {
        [$version, $venue] = $this->planning();
        $this->match($version, $venue, '2026-09-25', 'W001');

        $page = new VenueOccupancy;
        $page->versionId = $version->id;
        $page->fromDate = '01-09-2026';
        $page->toDate = '30-09-2026';

        $this->assertSame(1, $page->getOverviewProperty()['total']);

        $page->fromDate = '01-10-2026';
        $page->toDate = '31-10-2026';

        $this->assertSame(0, $page->getOverviewProperty()['total']);
    }

    public function test_the_calendar_groups_the_rows_per_month(): void
    {
        [$version, $venue] = $this->planning();
        $this->match($version, $venue, '2026-09-25', 'W001');
        $this->match($version, $venue, '2026-10-02', 'W002');

        $page = new VenueOccupancy;
        $page->versionId = $version->id;
        $page->fromDate = '01-09-2026';
        $page->toDate = '31-12-2026';

        $calendar = $page->getCalendarProperty();

        $this->assertCount(2, $calendar);
        $this->assertSame([$venue->name], collect($calendar)->flatten(1)->pluck('venue')->unique()->all());
    }

    public function test_the_list_shows_a_month_per_venue_and_loads_more_on_request(): void
    {
        [$version, $venue] = $this->planning();
        Availability::query()->create([
            'venue_id' => $venue->id, 'scope' => AvailabilityScope::Period,
            'period_start' => '2026-09-01', 'period_end' => '2026-11-30', 'status' => AvailabilityStatus::Available,
        ]);

        $page = new VenueOccupancy;
        $page->versionId = $version->id;
        $page->fromDate = '01-09-2026';
        $page->toDate = '30-11-2026';

        $this->assertSame(91, $page->getOverviewProperty()['total'], 'The counters cover the whole period.');
        $this->assertSame(VenueOccupancy::STEP, $page->shown($venue->id));

        $page->showMore($venue->id);
        $this->assertSame(2 * VenueOccupancy::STEP, $page->shown($venue->id));

        // Another filter starts at the first month again.
        $page->updated('venueId');
        $this->assertSame(VenueOccupancy::STEP, $page->shown($venue->id));
    }

    public function test_a_day_opens_and_closes(): void
    {
        $page = new VenueOccupancy;

        $page->toggleDay(3, '2026-10-10');
        $this->assertTrue($page->dayIsOpen(3, '2026-10-10'));
        $this->assertFalse($page->dayIsOpen(4, '2026-10-10'), 'The same day in another venue stays closed.');

        $page->toggleDay(3, '2026-10-10');
        $this->assertFalse($page->dayIsOpen(3, '2026-10-10'));
    }

    public function test_the_calendar_loads_in_steps(): void
    {
        [$version, $venue] = $this->planning();
        Availability::query()->create([
            'venue_id' => $venue->id, 'scope' => AvailabilityScope::Period,
            'period_start' => '2026-09-01', 'period_end' => '2027-03-31', 'status' => AvailabilityStatus::Available,
        ]);

        $page = new VenueOccupancy;
        $page->versionId = $version->id;
        $page->fromDate = '01-09-2026';
        $page->toDate = '31-03-2027';

        $shown = fn (): int => array_sum(array_map('count', $page->getCalendarProperty()));
        $this->assertSame(212, $page->getCalendarTotalProperty());
        $this->assertSame(3 * VenueOccupancy::STEP, $shown());

        $page->showMoreCalendar();
        $this->assertSame(6 * VenueOccupancy::STEP, $shown());
    }

    public function test_an_unreadable_date_falls_back_to_today_instead_of_crashing(): void
    {
        [$version] = $this->planning();

        $page = new VenueOccupancy;
        $page->versionId = $version->id;
        $page->fromDate = '01-09';
        $page->toDate = '';

        $this->assertSame(now()->toDateString(), $page->getOverviewProperty()['from']);
    }

    public function test_the_page_renders_list_and_calendar(): void
    {
        $this->actingAsClubUser();
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $version = ScheduleVersion::factory()->create();
        $this->match($version, $venue, '2026-09-25', 'W001');

        Livewire::test(VenueOccupancy::class)
            ->set('versionId', $version->id)
            ->set('fromDate', '01-09-2026')
            ->set('toDate', '30-09-2026')
            ->assertOk()
            ->assertSee('De Laar')
            ->call('toggleDay', $venue->id, '2026-09-25')
            ->assertSee('W001')
            ->set('display', 'calendar')
            ->assertOk();
    }

    /** @return array{0: ScheduleVersion, 1: Venue} */
    private function planning(): array
    {
        $this->useClub($this->createClub());
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $version = ScheduleVersion::factory()->create();

        Availability::query()->create([
            'venue_id' => $venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-09-25',
            'start_time' => '18:30', 'end_time' => '23:00', 'status' => AvailabilityStatus::Available,
        ]);

        return [$version, $venue];
    }

    private function match(ScheduleVersion $version, Venue $venue, string $date, string $code): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $version->id, 'venue_id' => $venue->id, 'date' => $date, 'time' => '19:00',
            'duration_minutes' => 105, 'match_type' => MatchType::Home, 'match_code' => $code,
        ]);
    }
}
