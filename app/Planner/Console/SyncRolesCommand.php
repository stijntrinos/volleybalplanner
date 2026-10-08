<?php

namespace App\Planner\Console;

use App\Models\Club;
use App\Planner\Support\ClubRole;
use App\Planner\Support\CommitteeRole;
use App\Planner\Support\PlannerPermissions;
use Illuminate\Console\Command;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolesCommand extends Command
{
    protected $signature = 'planner:sync-roles';

    protected $description = 'Maak de planner-permissies en -rollen aan of werk ze bij';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        $permissions = collect(PlannerPermissions::all())
            ->map(fn (string $name): PermissionContract => Permission::findOrCreate($name, 'web'));

        // The club admin may do everything, in every club.
        Role::findOrCreate(ClubRole::ClubAdmin->value, 'web')->syncPermissions($permissions);

        setPermissionsTeamId($previousTeam);
        $registrar->forgetCachedPermissions();

        // The planning committee has a role per club, with what that club allows.
        Club::query()->each(fn (Club $club): Role => CommitteeRole::sync($club));

        $this->info('Planner-rollen gesynchroniseerd.');

        return self::SUCCESS;
    }
}
