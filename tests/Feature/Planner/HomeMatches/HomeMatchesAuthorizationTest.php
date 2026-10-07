<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\User;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;
use Throwable;

/**
 * The day planner writes outside the Filament resources. The page permission
 * only covers "may this user open the page"; this test guards the rights on
 * the write actions. (Aetos ThuiswedstrijdenAutorisatieTest)
 */
class HomeMatchesAuthorizationTest extends TestCase
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
    }

    public function test_the_planning_committee_role_gets_rights_on_matches_and_planner_settings(): void
    {
        $role = Role::findByName(ClubRole::PlanningCommittee->value, 'web');

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $action) {
            $this->assertTrue($role->hasPermissionTo("{$action}:FixtureMatch"), "Permission {$action}:FixtureMatch is missing");
        }
        $this->assertTrue($role->hasPermissionTo('Update:PlannerSetting'));
    }

    public function test_who_may_view_the_planner_may_not_move_a_match_yet(): void
    {
        $this->user->removeClubRoles(app(CurrentClub::class)->require());
        $viewer = Role::findOrCreate('planner_viewer', 'web');
        $viewer->givePermissionTo(
            Permission::findByName('View:HomeMatches', 'web'),
            Permission::findByName('View:FixtureMatch', 'web'),
        );
        $this->user->unsetRelation('roles')->unsetRelation('permissions')->assignRole($viewer);
        $match = $this->match($this->version);

        $this->tryMove($this->version->id, $match->id);

        $this->assertNull($match->refresh()->venue_id, 'Without Update:FixtureMatch nothing may be moved');
    }

    public function test_with_the_right_permission_the_match_is_moved(): void
    {
        $match = $this->match($this->version);

        $this->tryMove($this->version->id, $match->id);

        $match->refresh();
        $this->assertSame($this->venue->id, $match->venue_id);
        $this->assertSame($this->court->id, $match->court_id);
    }

    public function test_a_match_from_another_version_stays_out_of_reach(): void
    {
        $otherVersion = ScheduleVersion::factory()->create();
        $match = $this->match($otherVersion);

        // The page shows $this->version; the call points to a match outside it.
        $this->tryMove($this->version->id, $match->id);

        $this->assertNull($match->refresh()->venue_id, 'A match outside the shown version may not change');
    }

    private function match(ScheduleVersion $version): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-19', 'time' => null, 'venue_id' => null, 'court_id' => null,
        ]);
    }

    /**
     * A refused call may come back as a 403/404 response or as an exception;
     * both are fine. What matters is that nothing was written.
     */
    private function tryMove(int $versionId, int $matchId): void
    {
        try {
            Livewire::test(HomeMatches::class)
                ->set('versionId', $versionId)
                ->set('date', '2026-09-19')
                ->call('move', $matchId, $this->venue->id, $this->court->id, '19:30');
        } catch (Throwable) {
            // Expected for a refused call or one outside the version.
        }
    }
}
