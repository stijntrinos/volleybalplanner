<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Dragging went through the conflict check, saving in the edit modal did not.
 * Via the form you could put a match on top of another without anything
 * saying so. (Aetos WedstrijdModalConflictTest)
 */
class MatchModalConflictTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    private Court $court;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
        $this->venue = Venue::factory()->create(['name' => 'Elderveld', 'is_active' => true, 'for_matches' => true]);
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1', 'sort_order' => 1]);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
    }

    public function test_saving_on_top_of_another_match_asks_for_confirmation_first(): void
    {
        $occupied = $this->match('BUSY', '10:45');
        $toMove = $this->match('MOVE', '12:30');

        $page = $this->openFor($toMove)
            ->set('modalTime', '10:45')
            ->call('saveMatch');

        $this->assertSame('12:30', substr((string) $toMove->refresh()->time, 0, 5), 'The match should not have moved yet.');
        $this->assertNotEmpty($page->get('confirmation'), 'A confirmation should appear.');
        $this->assertSame('modal', $page->get('confirmation')['source']);
        $this->assertContains('Het veld is al bezet.', array_column($page->get('confirmation')['conflicts'], 'message'));
        $this->assertSame('10:45', substr((string) $occupied->refresh()->time, 0, 5));
    }

    public function test_after_confirming_the_match_is_saved_after_all(): void
    {
        $this->match('BUSY', '10:45');
        $toMove = $this->match('MOVE', '12:30');

        $page = $this->openFor($toMove)
            ->set('modalTime', '10:45')
            ->call('saveMatch')
            ->call('confirmMove');

        $this->assertSame('10:45', substr((string) $toMove->refresh()->time, 0, 5));
        $this->assertSame([], $page->get('confirmation'));
    }

    public function test_cancelling_keeps_the_edit_modal_open(): void
    {
        $this->match('BUSY', '10:45');
        $toMove = $this->match('MOVE', '12:30');

        $page = $this->openFor($toMove)
            ->set('modalTime', '10:45')
            ->call('saveMatch')
            ->call('cancelConfirmation');

        $this->assertSame([], $page->get('confirmation'));
        $this->assertSame($toMove->id, $page->get('modalMatchId'), 'The modal should stay open for adjusting.');
        $this->assertSame('10:45', $page->get('modalTime'), 'The entered values should stay.');
        $this->assertSame('12:30', substr((string) $toMove->refresh()->time, 0, 5));
    }

    public function test_a_placement_without_conflict_just_saves(): void
    {
        $this->match('BUSY', '10:45');
        $toMove = $this->match('MOVE', '12:30');

        $page = $this->openFor($toMove)
            ->set('modalTime', '15:00')
            ->call('saveMatch');

        $this->assertSame('15:00', substr((string) $toMove->refresh()->time, 0, 5));
        $this->assertSame([], $page->get('confirmation'));
        $this->assertNull($page->get('modalMatchId'), 'The modal should close.');
    }

    public function test_putting_a_match_on_two_courts_fills_the_first_two_courts(): void
    {
        $second = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '2', 'sort_order' => 2]);
        $match = $this->match('W-2C', '10:45');

        $page = $this->openFor($match)->call('setTwoCourts', true);

        $page->assertHasNoErrors();
        $this->assertSame($this->court->id, $page->get('modalCourtId'));
        $this->assertSame($second->id, $page->get('modalSecondCourtId'));

        $page->call('saveMatch');

        $this->assertSame($this->court->id, $match->refresh()->court_id);
        $this->assertSame($second->id, $match->second_court_id);
    }

    public function test_switching_two_courts_off_clears_the_second_court(): void
    {
        $match = $this->match('W-1C', '10:45');

        $page = $this->openFor($match)
            ->call('setTwoCourts', true)
            ->call('setTwoCourts', false);

        $page->assertHasNoErrors();
        $this->assertNull($page->get('modalSecondCourtId'));
    }

    public function test_a_court_of_another_club_is_refused(): void
    {
        $match = $this->match('W-X', '10:45');
        $foreignCourtId = app(CurrentClub::class)->run($this->createClub(), fn (): int => Court::factory()->create()->id);

        $this->openFor($match)
            ->set('modalCourtId', $foreignCourtId)
            ->call('saveMatch')
            ->assertHasErrors('modalCourtId');

        $this->assertSame($this->court->id, $match->refresh()->court_id);
    }

    private function openFor(FixtureMatch $match): Testable
    {
        return Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->set('date', '2026-10-10')
            ->call('load')
            ->call('openMatch', $match->id);
    }

    private function match(string $code, string $time): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'date' => '2026-10-10', 'time' => $time, 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'match_code' => $code,
            'home_team_name' => 'VV Demo '.$code, 'away_team_name' => 'Tegenstander '.$code,
        ]);
    }
}
