<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Resources\Activities\Pages\CreateActivity;
use App\Planner\Filament\Resources\CalendarEvents\Pages\CreateCalendarEvent;
use App\Planner\Filament\Resources\HallDuties\Pages\CreateHallDuty;
use App\Planner\Filament\Resources\Teams\Pages\CreateTeam;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\Pages\ListTeams;
use App\Planner\Filament\Resources\Trainings\Pages\CreateTraining;
use App\Planner\Filament\Resources\Volleystars\Pages\CreateVolleystarsBlock;
use App\Planner\Models\Activity;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\CalendarEvent;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Records created through a resource belong to the current club, and the team
 * form only offers module fields when the module is on. Also the resource
 * part of Aetos VolleystarsPlannerKoppelingTest.
 */
class PlannerResourceRecordsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private int $clubId;

    private ScheduleVersion $version;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        // A second club with data, so a wrong club_id would stand out.
        $other = $this->createClub();
        app(CurrentClub::class)->run($other, fn () => ScheduleVersion::factory()->create());

        $this->actingAsClubUser();
        $this->clubId = app(CurrentClub::class)->id();
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->venue = Venue::factory()->create(['is_managed' => true, 'is_active' => true, 'for_trainings' => true]);
    }

    public function test_a_created_team_gets_the_current_club(): void
    {
        Livewire::test(CreateTeam::class)
            ->fillForm(['code' => 'HS1', 'name' => 'Club HS 1', 'locker_room_category' => 'boys_men'])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::query()->where('code', 'HS1')->firstOrFail();
        $this->assertSame($this->clubId, $team->club_id);
        $this->assertSame('boys_men', $team->locker_room_category);
    }

    public function test_a_created_calendar_event_gets_the_current_club(): void
    {
        ActivityColor::factory()->create(['type' => 'Vakantie']);

        Livewire::test(CreateCalendarEvent::class)
            ->fillForm(['name' => 'Herfstvakantie', 'type' => 'Vakantie', 'start_date' => '2026-10-17', 'end_date' => '2026-10-25'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->clubId, CalendarEvent::query()->firstOrFail()->club_id);
    }

    public function test_a_created_activity_gets_the_current_club_and_the_draft(): void
    {
        Livewire::test(CreateActivity::class)
            ->assertFormSet(['version_id' => $this->version->id])
            ->fillForm(['title' => 'Jeugdtoernooi', 'date' => '2026-11-07', 'start_time' => '09:00', 'venue_id' => $this->venue->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $activity = Activity::query()->firstOrFail();
        $this->assertSame($this->clubId, $activity->club_id);
        $this->assertSame($this->version->id, $activity->version_id);
    }

    public function test_a_created_training_gets_the_current_club_and_creator(): void
    {
        Livewire::test(CreateTraining::class)
            ->fillForm(['weekday' => 'dinsdag', 'start_time' => '19:00', 'end_time' => '20:30', 'venue_id' => $this->venue->id, 'court' => 1, 'source' => 'manual'])
            ->call('create')
            ->assertHasNoFormErrors();

        $training = Training::query()->firstOrFail();
        $this->assertSame($this->clubId, $training->club_id);
        $this->assertSame(auth()->id(), $training->created_by);
    }

    public function test_a_created_hall_duty_gets_the_current_club(): void
    {
        $team = Team::factory()->create();

        Livewire::test(CreateHallDuty::class)
            ->fillForm([
                'version_id' => $this->version->id, 'venue_id' => $this->venue->id, 'date' => '2026-11-07',
                'start_time' => '09:00', 'end_time' => '13:00', 'team_id' => $team->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->clubId, HallDuty::query()->firstOrFail()->club_id);
    }

    public function test_volleystars_times_use_clear_slots_and_validate(): void
    {
        $component = Livewire::test(CreateVolleystarsBlock::class)
            ->fillForm([
                'version_id' => $this->version->id,
                'date' => '2026-11-14',
                'venue_id' => $this->venue->id,
                'setup_start' => '08:00',
                'setup_end' => '07:45',
                'scoring_start' => '08:45',
                'scoring_end' => '12:30',
                'teardown_start' => '08:30',
                'teardown_end' => '12:45',
            ])
            ->call('create')
            ->assertHasFormErrors(['setup_end']);

        $component->fillForm(['setup_end' => '12:00'])
            ->call('create')
            ->assertHasNoFormErrors();

        $block = VolleystarsBlock::query()->firstOrFail();
        $this->assertSame($this->clubId, $block->club_id);
        $this->assertSame('08:00', substr((string) $block->setup_start, 0, 5));
        $this->assertSame('12:00', substr((string) $block->setup_end, 0, 5));
    }

    public function test_the_team_form_hides_module_fields_when_modules_are_off(): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(club: $club);
        $team = Team::factory()->create();

        // Locker rooms stay visible but locked, with an upgrade hint.
        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->assertFormFieldDoesNotExist('include_in_duties')
            ->assertFormFieldDoesNotExist('volunteer_points_used')
            ->assertFormFieldDisabled('locker_room_category');

        Livewire::test(ListTeams::class)->assertTableColumnHidden('minimum');
    }

    public function test_the_team_form_shows_module_fields_when_modules_are_on(): void
    {
        $team = Team::factory()->create();

        Livewire::test(EditTeam::class, ['record' => $team->getKey()])
            ->assertFormFieldExists('include_in_duties')
            ->assertFormFieldExists('volunteer_points_used')
            ->assertFormFieldExists('locker_room_category');

        $this->assertTrue(app(CurrentClub::class)->require()->hasFeature(PlannerFeature::VolunteerPoints));
    }
}
