<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use App\Planner\Services\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The planning assistant also checks a self-chosen date, time and venue, and
 * can mark a match as to be moved without a new place.
 * (Aetos PlanningsassistentEigenMomentTest)
 */
class PlanningAssistantOwnSlotTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    private FixtureMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-20 10:00:00');

        $this->actingAsClubUser();
        $this->venue = Venue::factory()->create(['is_active' => true, 'for_matches' => true]);
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match = $this->createMatch('W-1', '2026-10-10', '10:45');
    }

    public function test_a_free_moment_turns_green_and_can_be_applied(): void
    {
        $page = $this->assistant()
            ->set('ownDate', '2026-10-17')
            ->set('ownTime', '14:00')
            ->call('checkOwnSlot')
            ->assertHasNoErrors();

        $this->assertSame(AvailabilityChecker::RESULT_GREEN, $page->get('ownCheck')['check']['result']);

        $page->call('useOwnSlot')->assertSet('assistantMatchId', null);

        // To another day is a proposal: the match stays until approval, and
        // the new place is ready.
        $this->match->refresh();
        $this->assertSame('2026-10-10', $this->match->date->toDateString());
        $this->assertSame('2026-10-17', $this->match->provisional_date->toDateString());
        $this->assertSame('14:00', substr((string) $this->match->provisional_time, 0, 5));
    }

    public function test_an_occupied_court_gives_a_conflict_with_the_reason(): void
    {
        $this->createMatch('W-2', '2026-10-17', '14:00');

        $page = $this->assistant()
            ->set('ownDate', '2026-10-17')
            ->set('ownTime', '14:30')
            ->call('checkOwnSlot');

        $check = $page->get('ownCheck');
        $this->assertSame(AvailabilityChecker::RESULT_RED, $check['check']['result']);
        $this->assertNotEmpty($check['check']['reasons']);
        $page->assertSee('Dit moment geeft een conflict')->assertSee('Toch toepassen');

        // Only checking changes nothing in the planning.
        $this->assertSame('2026-10-10', $this->match->refresh()->date->toDateString());
    }

    public function test_the_own_moment_can_be_saved_as_a_proposal(): void
    {
        $this->assistant()
            ->set('ownDate', '2026-10-24')
            ->set('ownTime', '16:00')
            ->call('checkOwnSlot')
            ->call('saveOwnSlot');

        $this->match->refresh();
        $this->assertSame('2026-10-24', $this->match->away_new_date->toDateString());
        $this->assertSame('16:00', substr((string) $this->match->away_new_time, 0, 5));
        $this->assertSame('2026-10-10', $this->match->date->toDateString(), 'Saving does not move the match.');
    }

    public function test_without_date_or_time_there_is_nothing_to_check(): void
    {
        $this->assistant()
            ->set('ownDate', '')
            ->set('ownTime', 'later')
            ->call('checkOwnSlot')
            ->assertHasErrors(['ownDate', 'ownTime'])
            ->assertSet('ownCheck', null);
    }

    public function test_marking_puts_the_match_among_the_matches_still_to_plan(): void
    {
        $this->assistant()
            ->call('markToMove', $this->match->id)
            ->assertSet('assistantMatchId', null)
            ->set('showUnplanned', true)
            ->assertSee('Moet verplaatst worden')
            ->assertSee('Markering weghalen');

        $this->match->refresh();
        $this->assertSame(ChangeStatus::ToChange, $this->match->change_status);
        $this->assertSame('2026-10-10', $this->match->date->toDateString(), 'The match stays put.');

        $list = app(PlannerService::class)->dayData($this->version, '2026-10-10')['to_plan'];
        $this->assertSame([$this->match->id], array_column($list, 'id'));
        $this->assertTrue($list[0]['to_change']);
    }

    public function test_the_marker_can_be_removed_again(): void
    {
        $page = $this->assistant()->call('markToMove', $this->match->id);
        $page->call('clearMarker', $this->match->id);

        $this->assertNull($this->match->refresh()->change_status);
        $this->assertSame([], app(PlannerService::class)->dayData($this->version, '2026-10-10')['to_plan']);
    }

    public function test_moving_turns_the_marker_into_a_normal_request(): void
    {
        $this->assistant()
            ->call('markToMove', $this->match->id)
            ->call('move', $this->match->id, $this->venue->id, $this->court->id, '15:00');

        $this->match->refresh();
        $this->assertSame(ChangeStatus::Requested, $this->match->change_status);
        $this->assertTrue($this->match->change_requested);
    }

    public function test_a_published_schedule_cannot_be_marked(): void
    {
        $page = $this->assistant();
        $this->version->update(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);

        $page->call('markToMove', $this->match->id)->assertActionMounted('errorModal');
        $this->assertNull($this->match->refresh()->change_status);
    }

    private function assistant(): Testable
    {
        return Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->set('date', '2026-10-10')
            ->call('load')
            ->call('openAssistant', $this->match->id);
    }

    private function createMatch(string $code, string $date, string $time): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'match_code' => $code,
            'home_team_name' => 'VV Demo HS 1', 'away_team_name' => 'Tegenstander '.$code,
        ]);
    }
}
