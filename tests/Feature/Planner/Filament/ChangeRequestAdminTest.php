<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Resources\ChangeRequests\Pages\ListChangeRequests;
use App\Planner\Filament\Resources\ChangeRequests\Schemas\ChangeRequestInfolist;
use App\Planner\Filament\Widgets\NewChangeRequests;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Ported from Aetos WedstrijdverzoekAdminTest (the planner marking tests live in ChangeRequestServiceTest). */
class ChangeRequestAdminTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private FixtureMatch $match;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();

        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->team = Team::factory()->create(['code' => 'DS 1', 'name' => 'Club DS 1']);
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $venue->id,
            'match_code' => 'ABC1', 'date' => '2026-10-03', 'time' => '19:30', 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'home_team_name' => 'Club DS 1', 'away_team_name' => 'Trivos DS 1',
            'home_team_id' => $this->team->id,
        ]);
    }

    public function test_the_widget_only_shows_open_requests(): void
    {
        $open = $this->request(ChangeRequestStatus::Open);
        $handled = $this->request(ChangeRequestStatus::Accepted);

        Livewire::test(NewChangeRequests::class)
            ->assertCanSeeTableRecords([$open])
            ->assertCanNotSeeTableRecords([$handled]);
    }

    public function test_accepting_records_status_and_note(): void
    {
        $request = $this->request(ChangeRequestStatus::Open);

        Livewire::test(ListChangeRequests::class)
            ->callTableAction('accept', $request, data: ['note' => 'Verzet naar 10 oktober.']);

        $request->refresh();
        $this->assertSame(ChangeRequestStatus::Accepted, $request->status);
        $this->assertSame('Verzet naar 10 oktober.', $request->note);
        $this->assertNotNull($request->handled_at);
    }

    public function test_rejecting_works_too(): void
    {
        $request = $this->request(ChangeRequestStatus::Open);

        Livewire::test(ListChangeRequests::class)
            ->callTableAction('reject', $request, data: ['note' => 'Geen zaal beschikbaar.']);

        $this->assertSame(ChangeRequestStatus::Rejected, $request->refresh()->status);
    }

    public function test_parking_keeps_the_request_undecided_but_no_longer_new(): void
    {
        $request = $this->request(ChangeRequestStatus::Open);

        Livewire::test(ListChangeRequests::class)
            ->callTableAction('park', $request, data: ['note' => 'We vragen de tegenstander om een datum.']);

        $request->refresh();
        $this->assertSame(ChangeRequestStatus::Parked, $request->status);
        $this->assertSame('We vragen de tegenstander om een datum.', $request->note);

        // No longer in the new requests widget, still in the default list.
        Livewire::test(NewChangeRequests::class)->assertCanNotSeeTableRecords([$request]);
        Livewire::test(ListChangeRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertSee('Geparkeerd');

        // And the planner keeps marking the match: nothing has been decided.
        $day = app(PlannerService::class)->dayData($this->version, '2026-10-03');
        $this->assertTrue($day['matches'][0]['has_change_request']);
    }

    public function test_the_modal_shows_proposal_and_contact_preference(): void
    {
        $request = $this->request(ChangeRequestStatus::Open);
        $request->update([
            'proposed_date' => '2026-10-10',
            'proposed_time' => '17:00',
            'phone' => '06 12345678',
            'contact_preference' => ContactPreference::Whatsapp,
        ]);

        $texts = $this->infolistTexts($request);
        $this->assertContains('Zaterdag 10-10-2026 om 17:00', $texts);
        $this->assertContains('06 12345678', $texts);
        $this->assertContains('WhatsApp', $texts);
    }

    public function test_a_duty_request_is_in_the_same_list(): void
    {
        $request = ChangeRequest::factory()->create([
            'team_id' => $this->team->id,
            'match_id' => $this->match->id,
            'match_code' => $this->match->match_code,
            'kind' => ChangeRequestKind::Duty,
            'duty_type' => DutyType::Referee,
            'duty_date' => '2026-10-03',
            'duty_time' => '19:30',
            'duty_location' => 'Middachtensingel',
            'duty_description' => 'Club DS 1 – Trivos DS 1',
        ]);

        Livewire::test(ListChangeRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->assertSee('FTZ-dienst')
            ->assertSee('Fluiten');
    }

    public function test_the_modal_shows_the_whole_request_with_handling_buttons(): void
    {
        $request = $this->request(ChangeRequestStatus::Open);
        $request->update([
            'name' => 'Marieke de Vries',
            'explanation' => 'Het halve team zit op schoolkamp, we redden het echt niet met vier speelsters.',
        ]);

        $component = Livewire::test(ListChangeRequests::class)
            ->mountTableAction('view', $request)
            ->assertHasNoTableActionErrors();

        // A click on the row, not only the menu on the right, opens this request.
        $this->assertSame('view', $component->instance()->getTable()->getRecordAction($request));

        $texts = $this->infolistTexts($request);
        $this->assertContains('Marieke de Vries', $texts);
        $this->assertContains($request->fresh()->email, $texts);
        $this->assertContains('Het halve team zit op schoolkamp, we redden het echt niet met vier speelsters.', $texts);
        $this->assertContains('ABC1', $texts);
        $this->assertContains('03-10-2026 · 19:30', $texts);
        $this->assertContains('Middachtensingel', $texts);
    }

    public function test_the_modal_shows_the_details_of_a_duty_request(): void
    {
        $request = ChangeRequest::factory()->create([
            'team_id' => $this->team->id,
            'kind' => ChangeRequestKind::Duty,
            'duty_type' => DutyType::HallDuty,
            'duty_date' => '2026-10-03',
            'duty_time' => '19:30',
            'duty_location' => 'Middachtensingel',
        ]);

        Livewire::test(ListChangeRequests::class)
            ->mountTableAction('view', $request)
            ->assertHasNoTableActionErrors();

        $texts = $this->infolistTexts($request);
        $this->assertContains('Zaaldienst', $texts);
        $this->assertContains('03-10-2026 · 19:30', $texts);
        $this->assertContains('Middachtensingel', $texts);
    }

    /**
     * The texts the modal shows. Filament renders an action modal outside the
     * Livewire component, so assertSee on the page says nothing about it.
     *
     * @return list<string>
     */
    private function infolistTexts(ChangeRequest $request): array
    {
        // Fresh: name and email are only decrypted when read.
        $schema = ChangeRequestInfolist::configure(Schema::make(new ListChangeRequests)->record($request->fresh()));

        return collect($schema->getFlatComponents())
            ->filter(fn (Component $component): bool => $component instanceof TextEntry)
            ->map(fn (TextEntry $entry): string => (string) $entry->formatState($entry->getState()))
            ->values()
            ->all();
    }

    private function request(ChangeRequestStatus $status): ChangeRequest
    {
        return ChangeRequest::factory()->create([
            'match_id' => $this->match->id,
            'match_code' => 'ABC1',
            'team_id' => $this->team->id,
            'status' => $status,
        ]);
    }
}
