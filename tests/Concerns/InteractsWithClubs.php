<?php

namespace Tests\Concerns;

use App\Models\Club;
use App\Models\User;
use App\Planner\Features\PlannerFeature;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Filament\Facades\Filament;

/**
 * Test helpers for club tenancy.
 *
 *   $club = $this->createClub();                 // club with every planner module
 *   $this->useClub($club);                       // make it the current club (CurrentClub + Filament tenant)
 *   $user = $this->actingAsClubUser();           // planning committee member of a new club, logged in
 *   $user = $this->actingAsClubUser(ClubRole::ClubAdmin, $club);
 */
trait InteractsWithClubs
{
    private bool $plannerRolesSynced = false;

    /** @param  list<string>|null  $features  feature values; null means every planner module */
    protected function createClub(?array $features = null): Club
    {
        return Club::factory()->withFeatures($features ?? PlannerFeature::allValues())->create();
    }

    /** Makes the club current for models (BelongsToClub) and for Filament. */
    protected function useClub(Club $club): Club
    {
        app(CurrentClub::class)->set($club);
        Filament::setCurrentPanel('club');
        // TenantSet needs a user; without one the tenant is set quietly.
        Filament::setTenant($club, isQuiet: !auth()->check());

        return $club;
    }

    /**
     * Logs in a verified member of the club with the given role and makes the
     * club current. Creates the club when none is given.
     */
    protected function actingAsClubUser(ClubRole $role = ClubRole::PlanningCommittee, ?Club $club = null): User
    {
        if (!$this->plannerRolesSynced) {
            $this->artisan('planner:sync-roles')->assertSuccessful();
            $this->plannerRolesSynced = true;
        }

        $club ??= $this->createClub();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->clubs()->attach($club);
        $user->assignClubRole($club, $role);
        setPermissionsTeamId($club->getKey());

        $this->actingAs($user);
        $this->useClub($club);

        return $user;
    }
}
