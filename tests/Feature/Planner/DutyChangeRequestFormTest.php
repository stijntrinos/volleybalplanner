<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ChangeReason;
use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\DutyChangeRequestForm as Form;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\ChangeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutyChangeRequestFormTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private Team $ds1;

    private Team $hs1;

    private ScheduleVersion $version;

    private Venue $venue;

    private FixtureMatch $match;

    private HallDuty $hallDuty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-09-20 10:00:00');
        $this->club = $this->useClub($this->createClub());

        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $this->ds1 = Team::factory()->create(['code' => 'DS 1', 'name' => 'Demo DS 1']);
        $this->hs1 = Team::factory()->create(['code' => 'HS 1', 'name' => 'Demo HS 1']);
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->venue->id,
            'match_code' => 'FTZ-1', 'date' => '2026-10-03', 'time' => '19:30',
            'match_type' => MatchType::Home, 'home_team_name' => 'Demo DS 3', 'away_team_name' => 'Trivos DS 3',
            'referee' => 'DS1', 'scorer' => 'HS 1',
        ]);
        $this->hallDuty = HallDuty::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-10-04', 'start_time' => '09:00', 'end_time' => '13:00',
            'team_id' => $this->ds1->id, 'venue_id' => $this->venue->id,
        ]);
    }

    public function test_the_fixed_public_link_opens_the_duty_form(): void
    {
        $this->get(route('planner.public.duty-change-request', ['club' => $this->club]))
            ->assertOk()
            ->assertSee('FTZ-dienst wijzigen');
    }

    public function test_the_page_needs_both_the_change_requests_and_the_duties_module(): void
    {
        $url = route('planner.public.duty-change-request', ['club' => $this->club]);

        $this->setClubFeatures($this->club, [PlannerFeature::ChangeRequests->value]);
        $this->get($url)->assertNotFound();

        $this->setClubFeatures($this->club, [PlannerFeature::Duties->value]);
        $this->get($url)->assertNotFound();
    }

    public function test_duties_have_their_own_reasons_apart_from_match_requests(): void
    {
        $defaults = PlannerSetting::defaultDutyRequestReasons();

        $this->assertContains('We willen de dienst ruilen', $defaults);
        $this->assertNotContains('Vakantie of schoolactiviteit', $defaults);

        PlannerSetting::current()->update([
            'request_reasons' => ['Wedstrijdreden'],
            'duty_request_reasons' => ['FTZ-redenen op maat'],
        ]);

        $reasons = Livewire::test(Form::class)->instance()->getReasonsProperty();
        $this->assertSame(['FTZ-redenen op maat', ChangeReason::Other->value], $reasons);
    }

    public function test_a_team_only_sees_its_own_upcoming_duties(): void
    {
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->venue->id, 'match_code' => 'OLD',
            'date' => '2026-09-01', 'time' => '19:30', 'home_team_name' => 'Demo DS 4',
            'away_team_name' => 'Opponent', 'referee' => 'DS 1',
        ]);

        $duties = app(ChangeRequestService::class)->duties($this->ds1->id);

        $this->assertSame([DutyType::Referee->value, DutyType::HallDuty->value], $duties->pluck('type')->all());
        $this->assertSame(['FTZ-1'], $duties->pluck('match_code')->filter()->values()->all());
        $this->assertSame([$this->hallDuty->id], $duties->pluck('hall_duty_id')->filter()->values()->all());
    }

    public function test_a_complete_duty_request_lands_in_the_same_inbox(): void
    {
        Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('dutyKey', DutyType::Referee->value.':'.$this->match->id)
            ->set('reason', 'Team is niet beschikbaar')
            ->set('explanation', 'Ons team is die avond niet beschikbaar.')
            ->set('name', 'Jan Jansen')
            ->set('email', 'jan@example.com')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $request = ChangeRequest::query()->sole();
        $this->assertSame(ChangeRequestKind::Duty, $request->kind);
        $this->assertSame(DutyType::Referee, $request->duty_type);
        $this->assertSame($this->match->id, $request->match_id);
        $this->assertSame('FTZ-1', $request->match_code);
        $this->assertSame('2026-10-03', $request->duty_date->toDateString());
        $this->assertSame('19:30', $request->duty_time);
        $this->assertSame('jan@example.com', $request->email);
    }

    public function test_a_duty_request_stores_mobile_number_and_contact_preference(): void
    {
        Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('dutyKey', DutyType::Referee->value.':'.$this->match->id)
            ->set('reason', 'Team is niet beschikbaar')
            ->set('explanation', 'Ons team is die avond niet beschikbaar.')
            ->set('name', 'Jan Jansen')
            ->set('email', 'jan@example.com')
            ->set('contactPreference', ContactPreference::Whatsapp->value)
            ->set('phone', '0612345678')
            ->call('submit')
            ->assertHasNoErrors();

        $request = ChangeRequest::query()->sole();
        $this->assertSame('06 12345678', $request->phone);
        $this->assertSame(ContactPreference::Whatsapp, $request->contact_preference);
    }

    public function test_a_duty_of_another_team_is_refused(): void
    {
        Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('dutyKey', DutyType::Scorer->value.':'.$this->match->id)
            ->set('reason', 'Team is niet beschikbaar')
            ->set('explanation', 'Deze taak is niet van ons team.')
            ->set('name', 'Jan')
            ->set('email', 'jan@example.com')
            ->call('submit')
            ->assertHasErrors(['dutyKey']);

        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_choosing_another_team_clears_the_duty_choice(): void
    {
        Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('dutyKey', DutyType::Referee->value.':'.$this->match->id)
            ->set('teamId', $this->hs1->id)
            ->assertSet('dutyKey', '');
    }
}
