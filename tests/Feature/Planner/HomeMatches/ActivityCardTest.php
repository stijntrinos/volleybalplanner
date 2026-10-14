<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\Club;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * An activity created from the day planner blocks a court (or the whole
 * venue) for matches. (Aetos ActiviteitPlannerKaartjeTest)
 */
class ActivityCardTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = $this->createClub();
        $this->actingAsClubUser(club: $this->club);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel', 'is_active' => true, 'is_managed' => true]);
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1', 'is_active' => true]);
    }

    public function test_a_court_can_be_blocked_from_the_day_planner(): void
    {
        $this->newActivity()
            ->set('modalActivityTitle', 'Vaardigheidstraining')
            ->set('modalActivityVenueId', $this->venue->id)
            ->set('modalActivityCourtId', $this->court->id)
            ->set('modalActivityStartTime', '09:00')
            ->set('modalActivityEndTime', '12:00')
            ->call('saveActivity')
            ->assertHasNoErrors()
            ->assertSet('modalActivityId', null)
            ->assertSee('Vaardigheidstraining');

        $activity = Activity::query()->sole();
        $this->assertSame('2026-11-14', $activity->date->toDateString());
        $this->assertSame($this->court->id, $activity->court_id);

        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id]);
        $check = app(AvailabilityChecker::class)->check($match, [
            'date' => '2026-11-14', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ], collect(), false);
        $this->assertSame(AvailabilityChecker::RESULT_RED, $check['result']);
    }

    public function test_a_hall_wide_activity_is_on_the_timetable_and_can_be_deleted(): void
    {
        $activity = Activity::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-11-14',
            'start_time' => '09:00', 'end_time' => '12:00', 'title' => 'Jeugdtoernooi',
            'venue_id' => $this->venue->id, 'court_id' => null,
        ]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->call('selectDate', '2026-11-14')
            ->assertSee('Jeugdtoernooi')
            ->assertSee('hele zaal')
            ->call('openActivity', $activity->id)
            ->assertSet('modalActivityTitle', 'Jeugdtoernooi')
            ->call('deleteActivity');

        $this->assertModelMissing($activity);
    }

    public function test_an_end_time_before_the_start_time_is_refused(): void
    {
        $this->newActivity()
            ->set('modalActivityTitle', 'Opbouw')
            ->set('modalActivityVenueId', $this->venue->id)
            ->set('modalActivityStartTime', '12:00')
            ->set('modalActivityEndTime', '09:00')
            ->call('saveActivity')
            ->assertHasErrors('modalActivityEndTime');

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_court_of_another_club_is_refused(): void
    {
        $this->useClub($this->createClub());
        $foreignCourt = Court::factory()->create(['venue_id' => Venue::factory()->create()->id]);
        $this->useClub($this->club);

        $this->newActivity()
            ->set('modalActivityTitle', 'Opbouw')
            ->set('modalActivityVenueId', $this->venue->id)
            ->set('modalActivityCourtId', $foreignCourt->id)
            ->set('modalActivityStartTime', '09:00')
            ->set('modalActivityEndTime', '12:00')
            ->call('saveActivity')
            ->assertHasErrors('modalActivityCourtId');

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_a_read_only_club_cannot_add_an_activity(): void
    {
        $this->club->update(['trial_ends_at' => today()->subWeek()]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->assertDontSee(__('Activiteit toevoegen'))
            ->call('openNewActivity')
            ->assertNotified(__('Alleen bekijken'))
            ->assertSet('modalActivityId', null);
        $this->assertSame(0, Activity::query()->count());
    }

    private function newActivity(): Testable
    {
        return Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->call('selectDate', '2026-11-14')
            ->call('openNewActivity')
            ->assertSet('modalActivityId', 0);
    }
}
