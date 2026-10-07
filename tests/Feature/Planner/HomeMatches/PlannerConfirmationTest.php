<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\User;
use App\Planner\Enums\OverlapPolicy;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The confirmation on a conflict used window.confirm in JavaScript. Now it is
 * page state, so the message is readable and the question can be answered on
 * a phone too. (Aetos PlannerBevestigingTest)
 */
class PlannerConfirmationTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private User $user;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsClubUser();
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);

        UserPreference::query()->create(['user_id' => $this->user->id, 'overlap_policy' => OverlapPolicy::Warn]);
    }

    public function test_an_occupied_court_asks_for_confirmation_first(): void
    {
        [$standing, $moved] = $this->twoMatches();

        $page = $this->planner()->call('move', $moved->id, $this->venue->id, $this->court->id, '19:30');

        $confirmation = $page->get('confirmation');
        $this->assertSame($moved->id, $confirmation['match_id']);
        $this->assertNotEmpty($confirmation['conflicts']);
        $this->assertNull($moved->refresh()->time, 'Without confirmation nothing may have been written');
        $this->assertNotNull($standing->refresh()->time);
    }

    public function test_confirming_writes_the_move_after_all(): void
    {
        [, $moved] = $this->twoMatches();

        $page = $this->planner()
            ->call('move', $moved->id, $this->venue->id, $this->court->id, '19:30')
            ->call('confirmMove');

        $this->assertSame([], $page->get('confirmation'));
        $this->assertSame('19:30', substr((string) $moved->refresh()->time, 0, 5));
    }

    public function test_putting_back_leaves_the_planning_alone(): void
    {
        [, $moved] = $this->twoMatches();

        $page = $this->planner()
            ->call('move', $moved->id, $this->venue->id, $this->court->id, '19:30')
            ->call('cancelConfirmation');

        $this->assertSame([], $page->get('confirmation'));
        $this->assertNull($moved->refresh()->time);
    }

    public function test_the_match_modal_uses_the_agreed_date_and_time_notation(): void
    {
        [$match] = $this->twoMatches();

        $page = $this->planner()->call('openMatch', $match->id)
            ->assertSet('modalDate', '19-09-2026')
            ->assertSet('modalTime', '19:30')
            ->assertSee('placeholder="dd-MM-jjjj"', escape: false)
            ->assertSee('placeholder="HH:mm"', escape: false)
            ->set('modalDate', '20-09-2026')
            ->set('modalTime', '17:05')
            ->call('saveMatch');

        $page->assertHasNoErrors();
        $this->assertSame('2026-09-20', $match->refresh()->date?->toDateString());
        $this->assertSame('17:05', substr((string) $match->time, 0, 5));
    }

    public function test_the_planner_no_longer_shows_a_changed_marker(): void
    {
        [$match] = $this->twoMatches();
        $match->update([
            'original_date' => '2026-09-19',
            'original_time' => '18:00',
            'original_venue' => $this->venue->name,
            'original_court' => $this->court->name,
        ]);

        $this->planner()
            ->assertDontSee('Aangepast')
            ->assertDontSee('is-changed');
    }

    /** @return array{0: FixtureMatch, 1: FixtureMatch} */
    private function twoMatches(): array
    {
        $standing = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'STAND-1',
            'date' => '2026-09-19', 'time' => '19:30', 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'home_team_name' => 'VV Demo DS 1', 'away_team_name' => 'Orion DS 2',
        ]);
        $moved = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'DRAG-1',
            'date' => '2026-09-19', 'time' => null, 'duration_minutes' => 105,
            'venue_id' => null, 'court_id' => null,
            'home_team_name' => 'VV Demo HS 1', 'away_team_name' => 'Sliedrecht HS 4',
        ]);

        return [$standing, $moved];
    }

    private function planner(): Testable
    {
        return Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->set('date', '2026-09-19');
    }
}
