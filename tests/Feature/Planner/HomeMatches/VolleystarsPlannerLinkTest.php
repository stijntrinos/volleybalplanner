<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Teams can be linked to a Volleystars block straight from the day planner.
 * (Page parts of Aetos VolleystarsPlannerKoppelingTest)
 */
class VolleystarsPlannerLinkTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_teams_can_be_linked_directly_from_the_day_planner(): void
    {
        $this->actingAsClubUser();
        $version = ScheduleVersion::factory()->create();
        $teams = Team::factory()->count(4)->create();
        $block = $this->block($version, '2026-09-19');
        $block->tasks()->create(['role' => VolleystarsRole::Setup, 'team_id' => $teams[0]->id, 'sort_order' => 0]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->call('openVolleystars', $block->id)
            ->assertSet('modalVolleystarsTeams.setup.0', $teams[0]->id)
            ->set('modalVolleystarsTeams.setup', [$teams[0]->id, $teams[1]->id, $teams[2]->id])
            ->set('modalVolleystarsTeams.teardown', [$teams[3]->id, null, null])
            ->set('modalVolleystarsTeams.scoring', [$teams[1]->id, null, null])
            ->call('saveVolleystars')
            ->assertHasNoErrors()
            ->assertSet('modalVolleystarsId', null);

        $this->assertSame(
            [$teams[0]->id, $teams[1]->id, $teams[2]->id],
            $block->tasks()->where('role', VolleystarsRole::Setup)->orderBy('sort_order')->pluck('team_id')->all(),
        );
        $this->assertSame(5, $block->tasks()->count());
        $this->assertSame(0, $block->tasks()->whereNotNull('court_id')->count());
    }

    public function test_the_same_team_may_fill_several_places_of_a_task(): void
    {
        $this->actingAsClubUser();
        $version = ScheduleVersion::factory()->create();
        $team = Team::factory()->create();
        $block = $this->block($version, '2026-10-03');

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->call('openVolleystars', $block->id)
            ->set('modalVolleystarsTeams.setup', [$team->id, $team->id, $team->id])
            ->call('saveVolleystars')
            ->assertHasNoErrors()
            ->assertSet('modalVolleystarsId', null);

        $this->assertSame(
            [$team->id, $team->id, $team->id],
            $block->tasks()->where('role', VolleystarsRole::Setup)->orderBy('sort_order')->pluck('team_id')->all(),
        );
    }

    public function test_an_end_time_before_the_start_time_is_refused(): void
    {
        $this->actingAsClubUser();
        $version = ScheduleVersion::factory()->create();
        $block = $this->block($version, '2026-10-03');

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->call('openVolleystars', $block->id)
            ->set('modalVolleystarsTimes.setup', ['start' => '10:00', 'end' => '09:00'])
            ->call('saveVolleystars')
            ->assertHasErrors('modalVolleystarsTimes.setup.end');
    }

    public function test_without_the_volleystars_module_the_modal_is_unavailable(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub([]));
        $version = ScheduleVersion::factory()->create();
        $block = $this->block($version, '2026-10-03');

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->call('openVolleystars', $block->id)
            ->assertNotFound();
    }

    private function block(ScheduleVersion $version, string $date): VolleystarsBlock
    {
        return VolleystarsBlock::factory()->create([
            'version_id' => $version->id, 'date' => $date, 'venue_id' => Venue::factory()->create(['is_managed' => true])->id,
            'setup_start' => '08:00', 'setup_end' => '09:00', 'teardown_start' => '12:00', 'teardown_end' => '13:00',
            'scoring_start' => '09:00', 'scoring_end' => '12:00',
        ]);
    }
}
