<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutyBlockActionsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venueA;

    private Venue $venueB;

    /** A future Saturday: the planner leaves played days alone. */
    private string $saturday;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsClubUser();
        PlannerSetting::current()->update(['team_name_patterns' => ['VV Demo']]);

        $this->saturday = CarbonImmutable::now()->next('Saturday')->addWeeks(2)->toDateString();
        $this->version = ScheduleVersion::factory()->create();
        $this->venueA = Venue::factory()->create(['name' => 'Middachtensingel']);
        $this->venueB = Venue::factory()->create(['name' => 'Rijkerswoerd']);

        foreach (['HS 4', 'DS 2', 'JA 1'] as $code) {
            Team::factory()->create(['code' => $code, 'name' => 'VV Demo '.$code, 'include_in_duties' => true]);
        }
    }

    public function test_the_page_shows_matches_and_hall_duties_in_one_list(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $this->generated()
            ->assertSee('Middachtensingel')
            ->assertSee('Zaaldienst Middachtensingel')
            ->assertSee('Wedstrijd / dienst');
    }

    public function test_every_field_has_its_own_lock_with_explanation(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated();

        $component->assertSee('Vastzetten: de scheidsrechter blijft dan staan bij opnieuw genereren.')
            ->assertSee('Vastzetten: de teller blijft dan staan bij opnieuw genereren.')
            ->assertSee('Vastzetten: de zaaldienst blijft dan staan bij opnieuw genereren.')
            ->assertSee('Automatisch voorgesteld door de planner.');

        $component->call('lock', 0, 'scorer')
            ->assertSee('Vastgezet: de teller blijft staan bij opnieuw genereren. Klik om vrij te geven.')
            ->assertSee('Vastgezet: blijft staan bij opnieuw genereren.');
    }

    public function test_applying_a_block_leaves_the_other_venue_alone(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');
        $this->match($this->venueB, '10:00', 'W-B');

        $this->generated()
            ->call('applyBlock', $this->saturday, $this->venueA->id)
            ->assertHasNoErrors();

        $duties = HallDuty::query()->where('version_id', $this->version->id)->get();
        $this->assertCount(1, $duties);
        $this->assertSame($this->venueA->id, $duties->first()->venue_id);
        $this->assertNotNull(FixtureMatch::query()->where('match_code', 'W-A')->value('scorer'));
        $this->assertNull(FixtureMatch::query()->where('match_code', 'W-B')->value('scorer'));
    }

    public function test_applying_a_block_shows_no_warnings_of_other_days(): void
    {
        $nextSaturday = CarbonImmutable::parse($this->saturday)->addWeek()->toDateString();
        $target = $this->match($this->venueA, '10:00', 'W-1', $this->saturday);
        $otherDay = $this->match($this->venueA, '10:00', 'W-2', $nextSaturday);

        $component = $this->generated();

        $otherIndex = collect($component->get('draft.matches'))
            ->search(fn (array $row): bool => $row['id'] === $otherDay->id);
        $this->assertNotFalse($otherIndex);

        $component
            ->set("draft.matches.{$otherIndex}.scorer", 'ONBEKEND TEAM')
            ->call('applyBlock', $this->saturday, $this->venueA->id)
            ->assertDontSee('Het ingevulde team is niet gevonden.');

        $this->assertSame([], $component->get('validation.matches'));
        $this->assertNotNull($target->refresh()->scorer, 'the chosen block should be applied');
        $this->assertNull($otherDay->refresh()->scorer, 'the invalid other day should be left alone');
    }

    public function test_a_locked_assignment_survives_regeneration(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated()
            ->set('draft.matches.0.scorer', 'JA 1')
            ->call('lock', 0, 'scorer')
            ->call('generateBlock', $this->saturday, $this->venueA->id);

        $this->assertSame('JA 1', $component->get('draft.matches.0.scorer'));
        $this->assertTrue($component->get('draft.matches.0.locked.scorer'));
    }

    public function test_a_locked_assignment_stays_manual_when_applied(): void
    {
        $match = $this->match($this->venueA, '10:00', 'W-A');

        $this->generated()
            ->call('lock', 0, 'scorer')
            ->call('applyBlock', $this->saturday, $this->venueA->id);

        $this->assertFalse((bool) $match->refresh()->scorer_auto);
    }

    public function test_locking_a_scorer_does_not_freeze_the_referee(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated()
            ->set('draft.matches.0.referee', 'HANDMATIG')
            ->set('draft.matches.0.scorer', 'JA 1')
            ->call('lock', 0, 'scorer')
            ->call('generateBlock', $this->saturday, $this->venueA->id);

        $this->assertNotSame('HANDMATIG', $component->get('draft.matches.0.referee'));
        $this->assertSame('JA 1', $component->get('draft.matches.0.scorer'));
        $this->assertFalse($component->get('draft.matches.0.locked.referee'));
        $this->assertTrue($component->get('draft.matches.0.locked.scorer'));
    }

    public function test_a_whole_day_can_be_locked_while_empty_fields_stay_free(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');
        $this->match($this->venueB, '12:00', 'W-B');

        $component = $this->generated()
            ->set('draft.matches.0.scorer', '')
            ->call('lockDay', $this->saturday);

        $this->assertTrue($component->get('draft.matches.0.locked.referee'));
        $this->assertFalse($component->get('draft.matches.0.locked.scorer'));
        $this->assertTrue($component->get('draft.matches.1.locked.referee'));
        $this->assertTrue($component->get('draft.matches.1.locked.scorer'));
    }

    public function test_applying_only_locks_filled_in_fields(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated()
            ->set('draft.matches.0.scorer', '')
            ->call('applyBlock', $this->saturday, $this->venueA->id);

        $this->assertTrue($component->get('draft.matches.0.locked.referee'));
        $this->assertFalse($component->get('draft.matches.0.locked.scorer'));
    }

    public function test_a_locked_hall_duty_survives_applying_and_a_new_proposal(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $this->generated()
            ->set('draft.hall_duties.0.team_code', 'JA 1')
            ->call('lockHallDuty', 0)
            ->call('applyBlock', $this->saturday, $this->venueA->id);

        // A fresh proposal must read the lock from the database: without that
        // origin the planner would redistribute the manual choice.
        $fresh = Livewire::test(DutyPlanner::class)
            ->set('versionId', $this->version->id)
            ->set('mode', 'redistribute')
            ->call('generate');

        $this->assertTrue($fresh->get('draft.hall_duties.0.locked'));
        $this->assertSame('JA 1', $fresh->get('draft.hall_duties.0.team_code'));
    }

    public function test_a_locked_hall_duty_stays_manual_when_applied(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $this->generated()
            ->call('lockHallDuty', 0)
            ->call('applyBlock', $this->saturday, $this->venueA->id);

        $this->assertFalse((bool) HallDuty::query()->firstOrFail()->team_auto);
    }

    public function test_draft_and_locks_survive_a_page_reload(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $this->generated()
            ->set('draft.matches.0.scorer', 'JA 1')
            ->call('lock', 0, 'scorer');

        $reloaded = Livewire::test(DutyPlanner::class);

        $this->assertSame($this->version->id, $reloaded->get('versionId'));
        $this->assertSame('JA 1', $reloaded->get('draft.matches.0.scorer'));
        $this->assertTrue($reloaded->get('draft.matches.0.locked.scorer'));
    }

    public function test_applying_a_block_without_venue_keeps_the_hall_duty_of_the_venue(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');
        // A home match without venue forms its own block; that block must not
        // take the hall duties of the venues on the same day with it.
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_type' => MatchType::Home, 'match_code' => 'W-NONE',
            'date' => $this->saturday, 'time' => '12:00', 'venue_id' => null,
            'home_team_name' => 'VV Demo HS 5', 'away_team_name' => 'Tegenstander',
            'referee' => null, 'scorer' => null,
        ]);

        $this->generated()
            ->call('applyBlock', $this->saturday, $this->venueA->id)
            ->call('applyBlock', $this->saturday, null)
            ->assertHasNoErrors();

        $duties = HallDuty::query()->where('version_id', $this->version->id)->get();
        $this->assertCount(1, $duties, 'the hall duty of the venue should stay');
        $this->assertSame($this->venueA->id, $duties->first()->venue_id);
    }

    public function test_no_hall_duty_on_this_day_removes_the_duty_and_keeps_it_away(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated();
        $this->assertCount(1, $component->get('draft.hall_duties'));

        $component->call('toggleHallDutyDay', $this->saturday);

        $this->assertSame([], $component->get('draft.hall_duties'), 'the duty should disappear from the draft');
        $this->assertContains($this->saturday, PlannerSetting::current()->fresh()->hallDutyExceptions());

        // And a new proposal does not propose a team either.
        $component->call('generate');
        $this->assertSame([], $component->get('draft.hall_duties'));
    }

    public function test_the_exception_can_be_undone(): void
    {
        $this->match($this->venueA, '10:00', 'W-A');

        $component = $this->generated()
            ->call('toggleHallDutyDay', $this->saturday)
            ->call('toggleHallDutyDay', $this->saturday)
            ->call('generate');

        $this->assertCount(1, $component->get('draft.hall_duties'), 'another click restores the hall duty');
    }

    private function generated(): Testable
    {
        return Livewire::test(DutyPlanner::class)
            ->set('versionId', $this->version->id)
            ->set('fromDate', CarbonImmutable::parse($this->saturday)->subWeek()->format('d-m-Y'))
            ->set('toDate', CarbonImmutable::parse($this->saturday)->addWeeks(2)->format('d-m-Y'))
            ->call('generate');
    }

    private function match(Venue $venue, string $time, string $code, ?string $date = null): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_type' => MatchType::Home, 'match_code' => $code,
            'date' => $date ?? $this->saturday, 'time' => $time, 'venue_id' => $venue->id,
            'home_team_name' => 'VV Demo HS 5', 'away_team_name' => 'Tegenstander',
            'referee' => null, 'scorer' => null,
        ]);
    }
}
