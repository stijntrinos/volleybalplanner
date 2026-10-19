<?php

namespace App\Planner\Support;

use App\Models\Club;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Removes a club's planner data before the database runs its own cascade.
 *
 * Every planner table cascades on club_id, but hall duties, VolleyStars blocks
 * and trainings reference venues and teams with RESTRICT. That restriction is
 * deliberate (a venue in use must not silently disappear) but it makes the
 * cascade fail when the whole club is deleted. Deleting in dependency order
 * keeps the protection and lets a club go.
 *
 * Role assignments are scoped to the club (permission teams) without a
 * foreign key, so they and the club's own committee role go here too.
 */
final class ClubDataPurger
{
    /** From dependent to independent. */
    private const TABLES = [
        'planner_change_requests',
        'planner_volleystars_tasks',
        'planner_volleystars_blocks',
        'planner_hall_duties',
        'planner_activities',
        'planner_nevobo_changes',
        'planner_matches',
        'planner_trainings',
        'planner_availabilities',
        'planner_user_preferences',
        'planner_settings',
        'planner_schedule_versions',
        'planner_team_sources',
        'planner_teams',
        'planner_courts',
        'planner_venue_aliases',
        'planner_venues',
        'planner_calendar_events',
        'planner_activity_colors',
    ];

    public function purge(Club $club): void
    {
        DB::transaction(function () use ($club): void {
            foreach (self::TABLES as $table) {
                DB::table($table)->where('club_id', $club->getKey())->delete();
            }

            $this->purgeRoles($club);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function purgeRoles(Club $club): void
    {
        /** @var array{roles: string, model_has_roles: string, model_has_permissions: string} $tables */
        $tables = config('permission.table_names');
        $teamColumn = (string) config('permission.column_names.team_foreign_key', 'club_id');

        DB::table($tables['model_has_roles'])->where($teamColumn, $club->getKey())->delete();
        DB::table($tables['model_has_permissions'])->where($teamColumn, $club->getKey())->delete();
        // role_has_permissions cascades on the role.
        DB::table($tables['roles'])->where($teamColumn, $club->getKey())->delete();
    }
}
