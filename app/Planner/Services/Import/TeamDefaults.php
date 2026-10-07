<?php

namespace App\Planner\Services\Import;

use App\Planner\Enums\MatchType;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;

/**
 * Applies the home team's defaults to the home matches of a version: time,
 * venue, duration, court(s), scorer and referee. Only fills what is empty or
 * was filled automatically before, so manual planning is never overwritten.
 */
class TeamDefaults
{
    public function apply(ScheduleVersion $version): void
    {
        $version->matches()->with(['homeTeam', 'venue'])->get()->each(function (FixtureMatch $match): void {
            $team = $match->homeTeam;
            if (!$team || $match->match_type !== MatchType::Home) {
                return;
            }
            $updates = [];
            if (!$match->time && $team->default_time) {
                $updates['time'] = $team->default_time;
            }
            if (!$match->venue_id && $team->default_venue_id) {
                $updates['venue_id'] = $team->default_venue_id;
            }
            if (($match->duration_minutes === 120 || !$match->duration_minutes) && $team->default_match_duration) {
                $updates['duration_minutes'] = $team->default_match_duration;
            }
            if ($team->scores_own_match && !$match->scorer) {
                $updates += ['scorer' => $team->code, 'scorer_auto' => true];
            }
            if ($team->provides_referee && (!$match->referee || $match->referee_auto)) {
                $updates += ['referee' => 'Nevobo', 'referee_auto' => true, 'nevobo_referee' => true];
            } elseif (!$team->provides_referee && $match->referee_auto) {
                $updates += ['referee' => null, 'referee_auto' => false, 'nevobo_referee' => false];
            }
            $venueId = $updates['venue_id'] ?? $match->venue_id;
            if ($venueId && $team->default_court && !$match->court_id) {
                $updates['court_id'] = Court::query()->where('venue_id', $venueId)->orderBy('name')
                    ->skip($team->default_court - 1)->value('id');
            }
            $firstCourtId = $updates['court_id'] ?? $match->court_id;
            if ($team->plays_on_two_courts && $firstCourtId && !$match->second_court_id) {
                $firstCourt = Court::find($firstCourtId);
                $updates['second_court_id'] = Court::query()->where('venue_id', $venueId)
                    ->where('is_training_court', false)
                    ->whereKeyNot($firstCourtId)
                    ->orderByRaw('ABS(sort_order - ?)', [$firstCourt?->sort_order ?? 1])
                    ->orderBy('sort_order')
                    ->value('id');
            }
            if ($updates) {
                $match->update($updates);
            }
        });
    }
}
