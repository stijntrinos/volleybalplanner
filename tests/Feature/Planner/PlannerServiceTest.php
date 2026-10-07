<?php

namespace Tests\Feature\Planner;

use App\Models\User;
use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\NevoboStatus;
use App\Planner\Enums\OverlapPolicy;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Availability;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PlannerServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
    }

    public function test_day_data_contains_grid_and_matches_to_plan(): void
    {
        FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $this->venue->id, 'court_id' => $this->court->id]);
        FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertCount(1, $data['matches']);
        $this->assertCount(1, $data['venues']);
        $this->assertCount(1, $data['to_plan']);
    }

    /**
     * The committee wants a venue where there is only training that day in
     * the bar, so it can add it. It is not open: the timetable then got so
     * wide that the own venue got lost.
     */
    public function test_only_venues_with_a_home_match_are_open(): void
    {
        $trainingVenue = Venue::factory()->create(['name' => 'Alleen trainingen']);
        $this->training($trainingVenue, '10:00', '11:30');
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'match_type' => MatchType::Home,
        ]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertContains('Alleen trainingen', array_column($data['venues'], 'name'), 'The venue belongs in the bar.');
        $this->assertSame([$this->venue->id], $data['default_venues'], 'A venue with only a training should not be open.');
    }

    public function test_a_closed_venue_shows_no_trainings(): void
    {
        $open = Venue::factory()->create(['name' => 'Open zaal']);
        foreach ([$this->venue, $open] as $venue) {
            $this->training($venue, '10:00', '11:30');
        }
        Availability::factory()->create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Period,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'status' => AvailabilityStatus::Closed, 'note' => 'Renovatie',
        ]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertSame([$open->id], array_column($data['trainings'], 'venue_id'));
    }

    public function test_a_date_rule_available_lifts_the_closure_for_that_day(): void
    {
        $this->training($this->venue, '10:00', '11:30');
        Availability::factory()->create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Period,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-30', 'status' => AvailabilityStatus::Closed,
        ]);
        Availability::factory()->create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date,
            'date' => '2026-09-12', 'status' => AvailabilityStatus::Available,
        ]);

        $this->assertCount(1, $this->service()->dayData($this->version, '2026-09-12')['trainings']);
    }

    public function test_a_closure_with_times_only_removes_the_trainings_in_that_window(): void
    {
        foreach ([['09:00', '10:00'], ['19:00', '20:30']] as [$start, $end]) {
            $this->training($this->venue, $start, $end);
        }
        Availability::factory()->create([
            'venue_id' => $this->venue->id, 'scope' => AvailabilityScope::Date,
            'date' => '2026-09-12', 'start_time' => '18:00', 'end_time' => '23:00',
            'status' => AvailabilityStatus::Closed,
        ]);

        $trainings = $this->service()->dayData($this->version, '2026-09-12')['trainings'];

        $this->assertCount(1, $trainings);
        $this->assertSame('09:00', substr($trainings[0]['start_time'], 0, 5));
    }

    public function test_without_trainings_module_no_trainings_are_shown(): void
    {
        $this->useClub($this->createClub(array_values(array_diff(PlannerFeature::allValues(), ['trainings']))));
        $version = ScheduleVersion::factory()->create();
        $this->training(Venue::factory()->create(), '10:00', '11:30');

        $this->assertSame([], $this->service()->dayData($version, '2026-09-12')['trainings']);
    }

    public function test_without_matches_the_planning_falls_back_on_the_main_venue(): void
    {
        $main = Venue::factory()->create(['name' => 'Hoofdzaal']);
        Court::factory()->create(['venue_id' => $main->id]);
        PlannerSetting::current()->update(['main_venue_id' => $main->id]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertSame(['Hoofdzaal'], array_column($data['venues'], 'name'));
    }

    public function test_the_venue_of_an_away_match_is_not_put_next_to_the_home_venue(): void
    {
        $elsewhere = Venue::factory()->create(['name' => 'Zaal van de tegenstander']);
        Court::factory()->create(['venue_id' => $elsewhere->id]);

        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'match_type' => MatchType::Home,
        ]);
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '12:00',
            'venue_id' => $elsewhere->id, 'match_type' => MatchType::Away,
        ]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertSame([$this->venue->name], array_column($data['venues'], 'name'));
    }

    public function test_to_plan_also_contains_a_fully_planned_match_with_a_running_change(): void
    {
        $complete = ['version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $this->venue->id, 'court_id' => $this->court->id];
        FixtureMatch::factory()->create($complete);
        $changed = FixtureMatch::factory()->create($complete + ['change_status' => ChangeStatus::Requested]);

        $data = $this->service()->dayData($this->version, '2026-09-12');

        $this->assertSame([$changed->id], array_column($data['to_plan'], 'id'));
        $this->assertSame(ChangeStatus::Requested->value, $data['to_plan'][0]['change_status']);
    }

    public function test_to_plan_shows_the_original_planning_and_the_proposal(): void
    {
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'date' => '2026-09-19', 'time' => '12:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00',
            'original_venue' => $this->venue->name, 'original_court' => $this->court->name,
            'change_status' => ChangeStatus::Requested, 'change_requested' => true,
        ]);

        $card = collect($this->service()->dayData($this->version, '2026-09-19')['to_plan'])
            ->firstWhere('id', $match->id);

        $this->assertSame("12-09-2026 · 10:00 · {$this->venue->name} · veld {$this->court->name}", $card['original']);
        $this->assertSame("19-09-2026 · 12:00 · {$this->venue->name} · veld {$this->court->name}", $card['proposal']);
    }

    public function test_to_plan_supports_the_older_change_flag_without_status(): void
    {
        $changed = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'change_status' => null, 'change_requested' => true,
        ]);

        $data = $this->service()->dayData($this->version, $changed->date->toDateString());

        $this->assertSame([$changed->id], array_column($data['to_plan'], 'id'));
    }

    public function test_undo_still_works_minutes_after_the_move(): void
    {
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $result = $this->service()->move($match, $this->placement('2026-09-12', '10:00'));

        // The notification used to show for only ten seconds; nobody made that.
        $this->travel(5)->minutes();

        $this->assertTrue($this->service()->undo($result['undo_token']));
        $this->assertNull($match->refresh()->date);
    }

    public function test_moving_saves_and_can_be_undone(): void
    {
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $result = $this->service()->move($match, $this->placement('2026-09-12', '10:00'));

        $this->assertSame('2026-09-12', $match->refresh()->date->toDateString());
        $this->assertTrue($match->change_requested);
        $this->assertSame(ChangeStatus::Requested, $match->change_status);
        $this->assertSame(NevoboStatus::Open, $match->nevobo_status);
        $this->assertTrue($this->service()->undo($result['undo_token']));
        $this->assertNull($match->refresh()->date);
        $this->assertFalse($match->change_requested);
    }

    public function test_overlap_policy_asks_for_confirmation_on_conflict(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->create(['user_id' => $user->id, 'overlap_policy' => OverlapPolicy::Warn]);
        FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $this->venue->id, 'court_id' => $this->court->id]);
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $result = $this->service()->move($match, $this->placement('2026-09-12', '10:30'), $user->id);

        $this->assertTrue($result['confirmation_required']);
        $this->assertNull($match->refresh()->date);
    }

    public function test_overlap_policy_deny_refuses_a_conflict(): void
    {
        $user = User::factory()->create();
        UserPreference::factory()->create(['user_id' => $user->id, 'overlap_policy' => OverlapPolicy::Deny]);
        FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $this->venue->id, 'court_id' => $this->court->id]);
        $match = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $this->expectException(DomainException::class);

        $this->service()->move($match, $this->placement('2026-09-12', '10:30'), $user->id, confirmed: true);
    }

    public function test_an_empty_day_shows_match_venues_so_unplanned_matches_can_be_scheduled(): void
    {
        $this->venue->update(['for_matches' => true]);
        FixtureMatch::factory()->create(['version_id' => $this->version->id, 'date' => null, 'time' => null]);

        $data = $this->service()->dayData($this->version, '2026-10-03');

        $this->assertCount(1, $data['venues']);
        $this->assertSame($this->venue->id, $data['venues'][0]['id']);
    }

    public function test_a_published_planning_cannot_be_dragged(): void
    {
        $this->version->update(['status' => ScheduleVersionStatus::Published]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Alleen een conceptplanning kan worden gewijzigd.');

        $this->service()->move($match, ['duration_minutes' => 120] + $this->placement('2026-09-12', '11:00'));
    }

    public function test_a_change_request_can_be_cancelled_or_confirmed_as_applied_in_nevobo(): void
    {
        $newCourt = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '2']);
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'original_date' => '2026-09-12',
            'original_time' => '10:00', 'original_venue' => $this->venue->name, 'original_court' => $this->court->name,
        ]);
        $service = $this->service();
        $placement = $this->placement('2026-09-19', '12:00', $newCourt);

        $service->move($match, $placement, confirmed: true);
        $service->cancelChangeRequest($match->refresh());
        $this->assertSame('2026-09-12', $match->refresh()->date->toDateString());
        $this->assertSame($this->court->id, $match->court_id);

        $service->move($match, $placement, confirmed: true);
        $service->markNevoboApplied($match->refresh());
        $this->assertFalse($match->refresh()->change_requested);
        $this->assertSame(NevoboStatus::Applied, $match->nevobo_status);
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertSame('2', $match->original_court);
    }

    public function test_cancelling_restores_a_venue_that_nevobo_stored_as_an_alias(): void
    {
        $this->venue->aliases()->create(['name' => 'Sporthal '.$this->venue->name]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00',
            'original_venue' => 'Sporthal '.$this->venue->name.', Oude Straat 12, Arnhem',
            'original_court' => $this->court->name,
        ]);

        $service = $this->service();
        $service->move($match, $this->placement('2026-09-19', '12:00'), confirmed: true);
        $service->cancelChangeRequest($match->refresh());

        $match->refresh();
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $this->assertSame($this->venue->id, $match->venue_id);
        $this->assertSame($this->court->id, $match->court_id);
    }

    public function test_a_handled_nevobo_change_clears_the_old_and_new_audit_values(): void
    {
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00', 'original_venue' => $this->venue->name,
            'nevobo_change_status' => NevoboChangeStatus::Conflict, 'nevobo_changed_at' => now(),
            'nevobo_previous_date' => '2026-09-12', 'nevobo_previous_time' => '10:00', 'nevobo_previous_venue' => $this->venue->name,
            'nevobo_new_date' => '2026-09-19', 'nevobo_new_time' => '12:00', 'nevobo_new_venue' => $this->venue->name,
        ]);

        $this->service()->ignoreNevoboChange($match);

        $match->refresh();
        $this->assertSame(NevoboChangeStatus::None, $match->nevobo_change_status);
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertNull($match->nevobo_previous_date);
        $this->assertNull($match->nevobo_previous_time);
        $this->assertNull($match->nevobo_previous_venue);
        $this->assertNull($match->nevobo_new_date);
        $this->assertNull($match->nevobo_new_time);
        $this->assertNull($match->nevobo_new_venue);
    }

    public function test_accepting_a_nevobo_change_makes_it_the_planning_and_the_baseline(): void
    {
        $other = Venue::factory()->create(['name' => 'De Laar']);
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'nevobo_change_status' => NevoboChangeStatus::Conflict,
            'nevobo_new_date' => '2026-09-19', 'nevobo_new_time' => '12:00', 'nevobo_new_venue' => 'De Laar',
        ]);

        $this->service()->acceptNevoboChange($match);

        $match->refresh();
        $this->assertSame('2026-09-19', $match->date->toDateString());
        $this->assertSame('12:00', FixtureMatch::shortTime($match->time));
        $this->assertSame($other->id, $match->venue_id);
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertSame('De Laar', $match->original_venue);
        $this->assertSame(NevoboChangeStatus::None, $match->nevobo_change_status);
    }

    public function test_a_proposal_can_be_saved_and_removed_without_changing_the_planning(): void
    {
        $match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);
        $service = $this->service();

        $service->saveProposal($match, [
            'date' => '2026-09-19', 'time' => '12:00', 'duration_minutes' => 120,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $this->assertSame('2026-09-12', $match->refresh()->date->toDateString());
        $this->assertSame('2026-09-19', $match->away_new_date->toDateString());
        $this->assertSame(120, $match->suggested_duration_minutes);
        $this->assertSame($this->court->id, $match->suggested_court_id);
        $this->assertSame(AwayChangeStatus::InProgress, $match->away_change_status);

        $service->cancelProposal($match);

        $this->assertNull($match->refresh()->away_new_date);
        $this->assertNull($match->suggested_court_id);
        $this->assertSame(AwayChangeStatus::None, $match->away_change_status);
    }

    public function test_the_hall_duty_column_follows_the_configured_weekdays(): void
    {
        PlannerSetting::current()->update(['hall_duty_weekdays' => [6, 0]]);

        // 2026-09-12 is a Saturday, 2026-09-15 a Tuesday.
        $this->assertTrue($this->service()->dayData($this->version, '2026-09-12')['show_hall_duty']);
        $this->assertFalse($this->service()->dayData($this->version, '2026-09-15')['show_hall_duty']);
    }

    public function test_a_planned_hall_duty_stays_visible_on_an_unchecked_day(): void
    {
        PlannerSetting::current()->update(['hall_duty_weekdays' => [6, 0]]);
        HallDuty::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-15',
            'start_time' => '19:00', 'end_time' => '22:00', 'venue_id' => $this->venue->id,
            'team_id' => Team::factory()->create()->id,
        ]);

        $this->assertTrue($this->service()->dayData($this->version, '2026-09-15')['show_hall_duty']);
    }

    public function test_without_duties_module_there_is_no_hall_duty_column(): void
    {
        $this->useClub($this->createClub([]));
        $version = ScheduleVersion::factory()->create();

        $data = $this->service()->dayData($version, '2026-09-12');

        $this->assertFalse($data['show_hall_duty']);
        $this->assertSame([], $data['hall_duties']);
        $this->assertSame([], $data['volleystars']);
    }

    private function service(): PlannerService
    {
        return app(PlannerService::class);
    }

    private function training(Venue $venue, string $start, string $end): Training
    {
        return Training::factory()->create([
            'venue_id' => $venue->id, 'weekday' => 'zaterdag', 'start_time' => $start, 'end_time' => $end, 'court' => 1,
        ]);
    }

    /** @return array<string, mixed> */
    private function placement(string $date, string $time, ?Court $court = null): array
    {
        return [
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => ($court ?? $this->court)->id, 'second_court_id' => null,
        ];
    }
}
