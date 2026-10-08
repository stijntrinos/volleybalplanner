<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Livewire\PlannerTopbar;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The bell counts what needs a decision and lists each kind with its own
 * link, so the number always matches what you find behind it.
 */
class PlannerTopbarTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_bell_lists_requests_and_nevobo_changes_separately(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee);
        ChangeRequest::factory()->count(2)->create();
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
        FixtureMatch::factory()->create(['version_id' => $version->id, 'nevobo_change_status' => NevoboChangeStatus::Conflict]);

        Livewire::test(PlannerTopbar::class)
            ->assertSee('3 punten die aandacht vragen')
            ->assertSeeInOrder(['Nieuwe wijzigingsverzoeken', '2', 'Open Nevobo-wijzigingen', '1']);
    }

    public function test_without_anything_open_there_is_no_bell(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee);

        Livewire::test(PlannerTopbar::class)
            ->assertDontSee('vp-topbar-bell', false);
    }
}
