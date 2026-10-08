<?php

namespace Tests\Feature\Planner;

use App\Filament\Club\Resources\ClubUsers\Pages\ListClubUsers;
use App\Models\Club;
use App\Planner\Filament\Pages\Exports;
use App\Planner\Filament\Pages\Settings;
use App\Planner\Support\ClubRole;
use App\Planner\Support\CommitteeRole;
use App\Planner\Support\PlannerPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * A club admin decides what the planning committee of that club may do;
 * other clubs keep their own choice.
 */
class CommitteePermissionsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_every_planner_permission_is_in_a_group(): void
    {
        $this->assertEqualsCanonicalizing(PlannerPermissions::all(), PlannerPermissions::forGroups(PlannerPermissions::groups()));
    }

    public function test_by_default_the_committee_may_do_everything(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee);

        $this->assertTrue(Settings::canAccess());
        $this->assertTrue(Exports::canAccess());
    }

    public function test_a_club_admin_can_take_away_a_group(): void
    {
        $club = $this->createClub();
        $this->actingAsClubUser(ClubRole::ClubAdmin, $club);

        Livewire::test(ListClubUsers::class)
            ->callTableAction('committeePermissions', data: ['groups' => array_values(array_diff(PlannerPermissions::groups(), ['settings']))])
            ->assertHasNoTableActionErrors();

        $this->assertNotContains('settings', CommitteeRole::groups($club->refresh()));

        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);
        $this->assertFalse(Settings::canAccess());
        $this->assertTrue(Exports::canAccess());
        $this->get('/app/'.$club->slug.'/settings')->assertForbidden();
    }

    public function test_the_choice_of_one_club_does_not_affect_another(): void
    {
        $limited = $this->createClub();
        CommitteeRole::update($limited, ['planning']);
        $other = $this->createClub();

        $this->actingAsClubUser(ClubRole::PlanningCommittee, $other);

        $this->assertTrue(Settings::canAccess());
    }

    public function test_the_committee_cannot_change_the_rights(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee);

        $this->get('/app/'.Club::query()->latest('id')->value('slug').'/club-users')->assertForbidden();
    }
}
