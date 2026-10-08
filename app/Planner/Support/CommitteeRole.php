<?php

namespace App\Planner\Support;

use App\Models\Club;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The planning committee has a role per club, so each club admin decides
 * what the committee may do. The club admin role stays global (everything).
 */
final class CommitteeRole
{
    /** Creates or updates the club's committee role from its chosen groups. */
    public static function sync(Club $club): Role
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId($club->getKey());

        try {
            $role = Role::query()
                ->where('name', ClubRole::PlanningCommittee->value)
                ->where('guard_name', 'web')
                ->where($registrar->teamsKey, $club->getKey())
                ->first()
                ?? Role::create(['name' => ClubRole::PlanningCommittee->value, 'guard_name' => 'web', $registrar->teamsKey => $club->getKey()]);
            assert($role instanceof Role);

            // Only permissions that exist: planner:sync-roles creates them.
            $role->syncPermissions(Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', PlannerPermissions::forGroups(self::groups($club)))
                ->get());
        } finally {
            setPermissionsTeamId($previousTeam);
        }

        $registrar->forgetCachedPermissions();

        return $role;
    }

    /** @return list<string> the groups the committee has; every group until a club admin limits it */
    public static function groups(Club $club): array
    {
        $chosen = $club->committee_groups;

        return $chosen === null
            ? PlannerPermissions::groups()
            : array_values(array_intersect(PlannerPermissions::groups(), $chosen));
    }

    /** @param  list<string>  $groups */
    public static function update(Club $club, array $groups): void
    {
        $club->update(['committee_groups' => array_values(array_intersect(PlannerPermissions::groups(), $groups))]);
        self::sync($club);
    }
}
