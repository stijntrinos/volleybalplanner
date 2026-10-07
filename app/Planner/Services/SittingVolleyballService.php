<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubTeamMatcher;
use App\Support\Tenancy\CurrentClub;

/**
 * The club's first sitting volleyball team ("<club> XZ 1") plays its home
 * matches in a venue that is not marked for matches. A match of that team is
 * a home match (and sitting volleyball) only in a venue for matches;
 * elsewhere it is an away match. Without the sitting_volleyball module the
 * default type always applies.
 */
class SittingVolleyballService
{
    /** The team code of the sitting volleyball team. */
    public const TEAM_CODE = 'XZ 1';

    /** @return array{match_type: MatchType, is_sitting_volleyball: bool} */
    public function classify(string $homeTeam, string $awayTeam, ?Venue $venue, MatchType $defaultType): array
    {
        $default = ['match_type' => $defaultType, 'is_sitting_volleyball' => false];

        if (!app(CurrentClub::class)->require()->hasFeature(PlannerFeature::SittingVolleyball)) {
            return $default;
        }

        $matcher = ClubTeamMatcher::forCurrentClub();
        $isOurs = $matcher->startsWithOwnTeam(trim($homeTeam), self::TEAM_CODE)
            || $matcher->startsWithOwnTeam(trim($awayTeam), self::TEAM_CODE);
        if (!$isOurs) {
            return $default;
        }

        return $venue?->for_matches
            ? ['match_type' => MatchType::Home, 'is_sitting_volleyball' => true]
            : ['match_type' => MatchType::Away, 'is_sitting_volleyball' => false];
    }
}
