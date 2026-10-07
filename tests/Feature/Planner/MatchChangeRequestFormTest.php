<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ChangeReason;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Livewire\MatchChangeRequestForm as Form;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class MatchChangeRequestFormTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private Team $ds1;

    private FixtureMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-09-20 10:00:00');
        $this->club = $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['Demo']]);

        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $this->ds1 = Team::factory()->create(['code' => 'DS 1', 'name' => 'Demo DS 1']);
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'venue_id' => $venue->id,
            'match_code' => 'ABC1', 'date' => '2026-10-03', 'time' => '19:30', 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'home_team_name' => 'Demo DS 1', 'away_team_name' => 'Trivos DS 1',
            'home_team_id' => $this->ds1->id,
        ]);
    }

    public function test_the_form_opens_on_a_fixed_link(): void
    {
        Livewire::test(Form::class)->assertOk()->assertSet('open', true);

        $this->get(route('planner.public.match-change-request', ['club' => $this->club]))
            ->assertOk()
            ->assertSee('Wedstrijd verzetten')
            ->assertSee('noindex', false);
    }

    public function test_without_the_change_requests_module_the_page_does_not_exist(): void
    {
        $this->club->update(['features' => []]);

        $this->get(route('planner.public.match-change-request', ['club' => $this->club]))->assertNotFound();
    }

    public function test_a_closed_form_accepts_nothing(): void
    {
        PlannerSetting::current()->update(['requests_open' => false]);

        Livewire::test(Form::class)
            ->assertSet('open', false)
            ->set('teamId', $this->ds1->id)
            ->set('matchId', $this->match->id)
            ->set('reason', 'Vakantie of schoolactiviteit')
            ->set('explanation', 'De halve selectie is op schoolkamp.')
            ->set('name', 'Jan')
            ->set('email', 'jan@example.com')
            ->call('submit')
            ->assertSet('submitted', false);

        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_a_complete_request_comes_in(): void
    {
        $this->filledIn()
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $request = ChangeRequest::query()->sole();
        $this->assertSame('ABC1', $request->match_code);
        $this->assertSame($this->club->id, $request->club_id);
        $this->assertSame('jan@example.com', $request->email);
        $this->assertSame(ChangeRequestStatus::Open, $request->status);
    }

    public function test_a_request_can_carry_a_new_date_and_whatsapp_contact(): void
    {
        $this->filledIn()
            ->set('proposedDate', '2026-10-10')
            ->set('proposedTime', '17:00')
            ->set('contactPreference', ContactPreference::Whatsapp->value)
            ->set('phone', '+31 6 1234-5678')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSee('via WhatsApp');

        $request = ChangeRequest::query()->sole();
        $this->assertSame('2026-10-10', $request->proposed_date->toDateString());
        $this->assertSame('17:00', $request->proposed_time);
        $this->assertSame(ContactPreference::Whatsapp, $request->contact_preference);
        $this->assertSame('06 12345678', $request->phone);
        $this->assertSame('WhatsApp (06 12345678)', $request->contactText());
        // Encrypted, just like name and email.
        $this->assertStringNotContainsString('12345678', (string) ChangeRequest::query()->toBase()->value('phone'));
        $this->assertStringNotContainsString('jan@example.com', (string) ChangeRequest::query()->toBase()->value('email'));
    }

    public function test_whatsapp_without_a_number_is_refused(): void
    {
        $this->filledIn()
            ->set('contactPreference', ContactPreference::Whatsapp->value)
            ->call('submit')
            ->assertHasErrors(['phone' => 'required']);

        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_an_invalid_number_or_a_date_in_the_past_is_refused(): void
    {
        $this->filledIn()
            ->set('phone', '12345')
            ->set('proposedDate', '2026-09-01')
            ->call('submit')
            ->assertHasErrors(['phone', 'proposedDate']);
    }

    public function test_without_proposal_and_number_it_stays_with_email(): void
    {
        $this->filledIn()->call('submit')->assertHasNoErrors();

        $request = ChangeRequest::query()->sole();
        $this->assertNull($request->proposed_date);
        $this->assertNull($request->phone);
        $this->assertSame(ContactPreference::Email, $request->contact_preference);
    }

    public function test_all_fields_are_required(): void
    {
        Livewire::test(Form::class)
            ->call('submit')
            ->assertHasErrors(['teamId', 'matchId', 'reason', 'explanation', 'name', 'email']);

        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_with_other_the_reason_must_be_described(): void
    {
        $form = Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('matchId', $this->match->id)
            ->set('reason', ChangeReason::Other->value)
            ->set('explanation', 'Zie hieronder.')
            ->set('name', 'Jan')
            ->set('email', 'jan@example.com')
            ->call('submit');

        $form->assertHasErrors(['reasonOther']);

        $form->set('reasonOther', 'Zaal is bezet door een schoolgala.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('Zaal is bezet door een schoolgala.', ChangeRequest::query()->sole()->reason_other);
    }

    public function test_a_match_of_another_team_is_refused(): void
    {
        $other = Team::factory()->create(['code' => 'HS 1', 'name' => 'Demo HS 1']);

        Livewire::test(Form::class)
            ->set('teamId', $other->id)
            ->set('matchId', $this->match->id)
            ->set('reason', 'Vakantie of schoolactiviteit')
            ->set('explanation', 'Toelichting die lang genoeg is.')
            ->set('name', 'Jan')
            ->set('email', 'jan@example.com')
            ->call('submit')
            ->assertHasErrors(['matchId']);

        $this->assertSame(0, ChangeRequest::query()->count());
    }

    public function test_choosing_another_team_clears_the_chosen_match(): void
    {
        Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('matchId', $this->match->id)
            ->set('teamId', Team::factory()->create(['code' => 'HS 1'])->id)
            ->assertSet('matchId', null);
    }

    public function test_the_submission_rate_limiter_stops_a_flood(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->filledIn()->call('submit')->assertHasNoErrors();
        }

        $this->filledIn()->call('submit')->assertHasErrors(['email']);
        $this->assertSame(5, ChangeRequest::query()->count());
    }

    private function filledIn(): Testable
    {
        return Livewire::test(Form::class)
            ->set('teamId', $this->ds1->id)
            ->set('matchId', $this->match->id)
            ->set('reason', 'Vakantie of schoolactiviteit')
            ->set('explanation', 'De halve selectie is op schoolkamp.')
            ->set('name', 'Jan Jansen')
            ->set('email', 'jan@example.com');
    }
}
