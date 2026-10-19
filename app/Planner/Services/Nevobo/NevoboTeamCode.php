<?php

namespace App\Planner\Services\Nevobo;

use Illuminate\Support\Str;

/**
 * Turns a Nevobo team id ("/competitie/teams/cnh8q1u/meiden-b/3") into the
 * team code the planner uses ("MB 3"). The regular competition categories
 * have a fixed abbreviation; for the others (CMV, Volley Stars, sitting
 * volleyball, recreational) the code Nevobo itself puts at the end of the
 * team name is used, so it matches the names in the RSS feeds.
 */
final class NevoboTeamCode
{
    /** Nevobo team type => our category code. */
    private const TYPES = [
        'heren' => 'HS',
        'dames' => 'DS',
        'jongens-a' => 'JA',
        'meiden-a' => 'MA',
        'jongens-b' => 'JB',
        'meiden-b' => 'MB',
        'jongens-c' => 'JC',
        'meiden-c' => 'MC',
    ];

    /**
     * @return array{type: string, number: int}|null null when the id is not a Nevobo team id
     */
    public static function parseId(string $nevoboId): ?array
    {
        if (preg_match('#^/competitie/teams/[a-z0-9]+/([a-z0-9-]+)/(\d+)$#i', $nevoboId, $match) !== 1) {
            return null;
        }

        return ['type' => Str::lower($match[1]), 'number' => (int) $match[2]];
    }

    /** The planner code, e.g. "HS 1", "MA 2", "XZ 1" or "N6 3"; null for an unknown id format. */
    public static function fromNevobo(string $nevoboId, string $name): ?string
    {
        $parsed = self::parseId($nevoboId);

        if ($parsed === null) {
            return null;
        }

        if (isset(self::TYPES[$parsed['type']])) {
            return self::TYPES[$parsed['type']].' '.$parsed['number'];
        }

        // "AETOS XZ 1", "AETOS N6 2": the abbreviation before the number.
        if (preg_match('/(?<![\p{L}\p{N}])([A-Z][A-Z0-9+]{0,4})\s*'.$parsed['number'].'\s*$/u', trim($name), $match) === 1) {
            return $match[1].' '.$parsed['number'];
        }

        // No abbreviation in the name: initials of the type ("beach-heren" -> "BH").
        $initials = implode('', array_map(
            static fn (string $word): string => Str::upper($word[0]),
            array_filter(explode('-', $parsed['type']), static fn (string $word): bool => $word !== ''),
        ));

        return $initials.' '.$parsed['number'];
    }

    /**
     * Whether the team type plays the regular competition. CMV and Volley
     * Stars are youth formats with tournament days, which most clubs plan
     * separately, so they are not preselected.
     */
    public static function isRegularCompetition(string $nevoboId, ?string $standingText): bool
    {
        $parsed = self::parseId($nevoboId);

        if ($parsed === null) {
            return false;
        }

        if (isset(self::TYPES[$parsed['type']])) {
            return true;
        }

        return !str_starts_with($parsed['type'], 'cmv') && !str_starts_with($parsed['type'], 'volley-stars')
            && trim((string) $standingText) !== '';
    }
}
