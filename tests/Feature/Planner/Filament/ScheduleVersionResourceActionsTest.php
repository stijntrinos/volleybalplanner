<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\CreateScheduleVersion;
use App\Planner\Filament\Resources\ScheduleVersions\Pages\ListScheduleVersions;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Ported from Aetos VersieResourceActionsTest. */
class ScheduleVersionResourceActionsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
    }

    public function test_the_publish_action_publishes_the_version(): void
    {
        $version = ScheduleVersion::factory()->create();

        Livewire::test(ListScheduleVersions::class)
            ->callTableAction('publish', $version);

        $this->assertTrue($version->refresh()->isPublished());
    }

    /**
     * A blocked publication used to end with a generic error and an open
     * confirmation. Now the confirmation makes way for the conflict overview.
     */
    public function test_a_blocked_publication_opens_the_conflict_overview(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $version = ScheduleVersion::factory()->create();
        Team::factory()->create(['code' => 'HS1']);
        FixtureMatch::factory()->count(2)->create([
            'version_id' => $version->id,
            'date' => '2026-09-12', 'time' => '10:00', 'home_team_name' => 'AETOS HS1',
        ]);

        $test = Livewire::test(ListScheduleVersions::class)
            ->callTableAction('publish', $version)
            ->assertActionMounted('publishConflicts');

        $arguments = $test->instance()->mountedActions[0]['arguments'];
        $this->assertSame($version->name, $arguments['version']);
        $this->assertContains(
            'Team HS1 heeft een overlappende wedstrijd.',
            collect($arguments['conflicts'])->pluck('message')->all(),
        );
        $this->assertFalse($version->refresh()->isPublished());
    }

    public function test_the_duplicate_action_makes_a_copy(): void
    {
        $version = ScheduleVersion::factory()->create();

        Livewire::test(ListScheduleVersions::class)
            ->callTableAction('duplicate', $version, data: ['name' => 'Kopie A']);

        $this->assertDatabaseHas('planner_schedule_versions', [
            'name' => 'Kopie A',
            'status' => ScheduleVersionStatus::Draft->value,
            'club_id' => $version->club_id,
        ]);
    }

    public function test_a_created_version_is_a_draft_of_the_current_club(): void
    {
        Livewire::test(CreateScheduleVersion::class)
            ->fillForm(['name' => 'Concept 2027'])
            ->call('create')
            ->assertHasNoFormErrors();

        $version = ScheduleVersion::query()->where('name', 'Concept 2027')->firstOrFail();
        $this->assertSame(ScheduleVersionStatus::Draft, $version->status);
        $this->assertSame(auth()->id(), $version->created_by);
        $this->assertSame(Filament::getTenant()->getKey(), $version->club_id);
    }
}
