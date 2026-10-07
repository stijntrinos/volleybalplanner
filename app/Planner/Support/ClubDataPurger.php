<?php

namespace App\Planner\Support;

use App\Models\Club;
use Illuminate\Support\Facades\DB;

/**
 * Removes a club's planner data before the database runs its own cascade.
 *
 * Every planner table cascades on club_id, but hall duties, VolleyStars blocks
 * and trainings reference venues and teams with RESTRICT. That restriction is
 * deliberate (a venue in use must not silently disappear) but it makes the
 * cascade fail when the whole club is deleted. Deleting in dependency order
 * keeps the protection and lets a club go.
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
        });
    }
}
