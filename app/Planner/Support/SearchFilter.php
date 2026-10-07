<?php

namespace App\Planner\Support;

use Illuminate\Support\Str;

/**
 * Searching on the public match overview. The whole search term used to be
 * compared as one literal text, so "ds 1 arnhem" never found anything and
 * "ds1" missed the team "DS 1". Here the term is cut into separate words that
 * must all occur, and accents and spaces are ignored.
 */
final class SearchFilter
{
    /**
     * Words visitors use for a whole kind of team, with the team code the
     * planning uses for it. Whoever searches "dames" means all DS teams, not
     * the literal text "dames". The words are Dutch because visitors type them.
     */
    private const TEAM_KINDS = [
        'dames' => 'ds',
        'heren' => 'hs',
    ];

    /** Lower case without accents and with single spaces. */
    public static function normalize(mixed $value): string
    {
        $text = Str::lower(Str::ascii(trim((string) $value)));

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /** Same as normalize, but without spaces, so "ds1" and "DS 1" are equal. */
    public static function withoutSpaces(mixed $value): string
    {
        return str_replace(' ', '', self::normalize($value));
    }

    /**
     * Whether all words of the search term occur in the text. Each word may
     * match either the normalised text or the version without spaces, so
     * "ds1" also finds "DS 1".
     */
    public static function contains(string $text, string $term): bool
    {
        $words = array_filter(explode(' ', self::normalize($term)));

        if ($words === []) {
            return true;
        }

        $normalized = self::normalize($text);
        $condensed = self::withoutSpaces($text);

        foreach ($words as $word) {
            if (!self::wordOccurs($normalized, $condensed, $word)) {
                return false;
            }
        }

        return true;
    }

    private static function wordOccurs(string $normalized, string $condensed, string $word): bool
    {
        if (str_contains($normalized, $word)
            || str_contains($condensed, self::withoutSpaces($word))) {
            return true;
        }

        $teamCode = self::TEAM_KINDS[$word] ?? null;

        // The code must be at the start of a word, otherwise "ds" would also
        // match in the middle of an opponent or venue name.
        return $teamCode !== null
            && preg_match('/\b'.preg_quote($teamCode, '/').'/', $normalized) === 1;
    }

    /**
     * Whether a team code from a duty points at the same team as the team
     * chosen from the match list. Duties store "DS 1", the match list
     * "Club Sponsor DS 1"; comparing on the end keeps "DS 1" and "DS 10"
     * apart, which searching on part of the text cannot.
     */
    public static function isSameTeam(?string $chosenTeam, ?string $teamCode): bool
    {
        $code = self::withoutSpaces($teamCode);

        if ($code === '' || blank($chosenTeam)) {
            return false;
        }

        return str_ends_with(self::withoutSpaces($chosenTeam), $code);
    }
}
