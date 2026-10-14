<?php

namespace App\Planner\Services;

use App\Planner\Models\Activity;
use App\Planner\Support\Timetable;
use Illuminate\Support\Collection;

/**
 * An activity keeps a court free, for example for a skills training; no match
 * belongs there. An activity without a court blocks the whole venue.
 *
 * Split out of ConflictEngine to keep that class readable.
 */
final class ActivityConflicts
{
    /**
     * @param  Collection<int, Activity>  $activities  the activities of the version on the placement's date
     * @param  list<int>  $courtIds
     * @return list<array{level: string, type: string, message: string}>
     */
    public static function findings(Collection $activities, int $venueId, array $courtIds, string $time, int $duration): array
    {
        $findings = [];
        $start = Timetable::minutes($time);

        foreach ($activities as $activity) {
            if ((int) $activity->venue_id !== $venueId || !$activity->start_time) {
                continue;
            }
            if ($activity->court_id && !in_array((int) $activity->court_id, $courtIds, true)) {
                continue;
            }
            $activityStart = Timetable::minutes($activity->start_time);
            $activityDuration = $activity->end_time ? Timetable::minutes($activity->end_time) - $activityStart : 60;
            if ($activityDuration > 0 && $start < $activityStart + $activityDuration && $activityStart < $start + $duration) {
                $findings[] = [
                    'level' => ConflictEngine::LEVEL_CONFLICT, 'type' => ConflictEngine::TYPE_ACTIVITY,
                    'message' => __('Het veld is bezet door een activiteit: :title.', ['title' => $activity->title ?: __('zonder titel')]),
                ];
            }
        }

        return $findings;
    }
}
