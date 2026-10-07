<?php

namespace App\Planner\Support;

use Illuminate\Support\Str;

/**
 * Team codes come from Nevobo files, RSS feeds, manual input and the team
 * table, and are written slightly differently everywhere: "HS 1", "HS1",
 * "hs-1". Comparing and grouping goes through this key so the duty overview
 * does not turn one team into three rows.
 *
 * Recognising the club itself in a match name ("AETOS HS 1") is the job of
 * ClubTeamMatcher; isOwnTeam() and isOwnTeamWithCode() delegate to it.
 */
final class TeamCode
{
    public const CATEGORY_OTHER = 'other';

    /** The order in which the groups are listed. */
    public const CATEGORY_ORDER = [
        'men', 'women', 'boys_a', 'girls_a', 'boys_b', 'girls_b', 'boys_c', 'girls_c', self::CATEGORY_OTHER,
    ];

    private const CATEGORIES = [
        'HS' => 'men', 'H' => 'men',
        'DS' => 'women', 'D' => 'women',
        'JA' => 'boys_a', 'MA' => 'girls_a',
        'JB' => 'boys_b', 'MB' => 'girls_b',
        'JC' => 'boys_c', 'MC' => 'girls_c',
    ];

    /** Characters that distinguish a team code; the rest is spelling. */
    private const DISTINGUISHING = 'A-Z0-9+';

    /** A category missing here sorts the team last, by name. */
    private const RANK = [
        'HS' => 1, 'H' => 1,
        'DS' => 2, 'D' => 2,
        'JA' => 3, 'MA' => 4,
        'JB' => 5, 'MB' => 6,
        'JC' => 7, 'MC' => 8,
    ];

    public static function key(?string $value): string
    {
        return Str::upper(preg_replace('/[^A-Z0-9]/i', '', (string) $value) ?? '');
    }

    /**
     * The regex that finds this code in a free-form match name. Spelling may
     * vary between the characters ("HS 1", "HS1", "hs-1") and the boundaries
     * keep "HS 1" out of "HS 10". The plus of "MA+" counts as a character:
     * without it that team was read as the regular MA line and recognised
     * itself in every "MA 4".
     */
    public static function pattern(?string $value): ?string
    {
        $characters = Str::upper(preg_replace('/[^'.self::DISTINGUISHING.']/i', '', (string) $value) ?? '');

        if ($characters === '') {
            return null;
        }

        $core = implode('[^'.self::DISTINGUISHING.']*', array_map(
            static fn (string $character): string => preg_quote($character, '/'),
            str_split($characters),
        ));

        return '/(?<!['.self::DISTINGUISHING.'])'.$core.'(?!['.self::DISTINGUISHING.'])/i';
    }

    public static function equals(?string $left, ?string $right): bool
    {
        return self::key($left) !== '' && self::key($left) === self::key($right);
    }

    /** Whether this match name is a team of the current club. See ClubTeamMatcher::isOwnTeam(). */
    public static function isOwnTeam(?string $name): bool
    {
        return ClubTeamMatcher::forCurrentClub()->isOwnTeam($name);
    }

    /** Whether this match name is the current club's team with this code. See ClubTeamMatcher::isOwnTeamWithCode(). */
    public static function isOwnTeamWithCode(?string $name, ?string $code): bool
    {
        return ClubTeamMatcher::forCurrentClub()->isOwnTeamWithCode($name, $code);
    }

    /**
     * Sort key in the order clubs name their teams: men, women, then A, B and
     * C youth with boys before girls, and finally groups without a number.
     * Alphabetical sorting put "DS 10" between DS 1 and DS 2.
     */
    public static function sortKey(?string $value): string
    {
        // On the raw text with word boundaries, not on the key: a team called
        // "Club Sponsor DS 1" would otherwise read as one word.
        preg_match('/\b(HS|DS|JA|MA|JB|MB|JC|MC|H|D)\s*(\d+)\s*$/i', (string) $value, $match);

        return sprintf(
            '%02d|%04d|%s',
            self::RANK[Str::upper($match[1] ?? '')] ?? 99,
            isset($match[2]) ? (int) $match[2] : 0,
            self::key($value),
        );
    }

    /**
     * The group this team belongs to (one of CATEGORY_ORDER). A code without
     * a category ("Recreanten", "MA+") is "other": those groups play no
     * competition and fit neither men, women nor youth.
     */
    public static function category(?string $value): string
    {
        preg_match('/^\s*(HS|DS|JA|MA|JB|MB|JC|MC|H|D)\s*\d+\s*$/i', (string) $value, $match);

        return self::CATEGORIES[Str::upper($match[1] ?? '')] ?? self::CATEGORY_OTHER;
    }

    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            'men' => __('Heren'),
            'women' => __('Dames'),
            'boys_a' => __('Jongens A'),
            'girls_a' => __('Meisjes A'),
            'boys_b' => __('Jongens B'),
            'girls_b' => __('Meisjes B'),
            'boys_c' => __('Jongens C'),
            'girls_c' => __('Meisjes C'),
            default => __('Overig'),
        };
    }
}
