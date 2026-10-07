<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Models\User;
use App\Planner\Models\Activity;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\Availability;
use App\Planner\Models\CalendarEvent;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\NevoboChange;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Planner\Policies\PlannerPolicy;
use App\Planner\Support\ClubRole;
use App\Planner\Support\PlannerPermissions;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The role and permission parts of the Aetos access tests: the planning
 * committee may manage every planner model, a member without a role may not,
 * and a read-only page permission grants no write access.
 */
class PlannerPermissionsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const MODELS = [
        Activity::class, ActivityColor::class, Availability::class, CalendarEvent::class, ChangeRequest::class,
        Court::class, FixtureMatch::class, HallDuty::class, NevoboChange::class, PlannerSetting::class,
        ScheduleVersion::class, Team::class, TeamSource::class, Training::class, Venue::class, VenueAlias::class,
        VolleystarsBlock::class, VolleystarsTask::class,
    ];

    public function test_every_model_has_a_planner_policy_and_permissions(): void
    {
        foreach (self::MODELS as $model) {
            $this->assertInstanceOf(PlannerPolicy::class, Gate::getPolicyFor($model), $model);
        }

        $this->assertCount(18, PlannerPermissions::MODELS);
        $this->assertContains('ViewAny:FixtureMatch', PlannerPermissions::all());
        $this->assertContains('View:HomeMatches', PlannerPermissions::all());
        $this->assertContains('View:NewChangeRequests', PlannerPermissions::all());
    }

    public function test_the_planning_committee_may_manage_every_planner_model(): void
    {
        $user = $this->actingAsClubUser();

        foreach (self::MODELS as $model) {
            $record = $model::factory()->create();

            foreach (['viewAny', 'create', 'deleteAny'] as $ability) {
                $this->assertTrue($user->can($ability, $model), "{$ability} {$model}");
            }

            foreach (['view', 'update', 'delete'] as $ability) {
                $this->assertTrue($user->can($ability, $record), "{$ability} {$model}");
            }
        }
    }

    public function test_a_member_without_a_role_gets_no_access(): void
    {
        $this->artisan('planner:sync-roles');
        $club = $this->useClub($this->createClub());
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->clubs()->attach($club);
        setPermissionsTeamId($club->id);

        $this->assertFalse($user->can('viewAny', Team::class));
        $this->assertFalse($user->can('viewAny', ScheduleVersion::class));
    }

    public function test_a_role_in_another_club_grants_nothing_here(): void
    {
        $user = $this->actingAsClubUser();
        $other = $this->useClub($this->createClub());
        $user->clubs()->attach($other);
        setPermissionsTeamId($other->id);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $this->assertFalse($user->can('viewAny', Team::class));
    }

    public function test_a_viewer_may_not_update_matches_or_tick_off_nevobo_changes(): void
    {
        $user = $this->actingAsClubUser(ClubRole::PlanningCommittee);
        $user->removeClubRoles($this->currentClub());
        $viewer = Role::findOrCreate('planner_viewer', 'web');
        $viewer->givePermissionTo(Permission::findByName('View:AwayMatches', 'web'));
        $user->unsetRelation('roles')->unsetRelation('permissions')->assignRole($viewer);

        $change = NevoboChange::factory()->create();

        $this->assertTrue($user->can('View:AwayMatches'));
        $this->assertFalse($user->can('update', $change->match));
        $this->assertFalse($user->can('update', $change));
    }

    private function currentClub(): Club
    {
        return app(CurrentClub::class)->require();
    }
}
