<?php

namespace App\Planner\Support;

use App\Models\Club;
use App\Planner\Models\PlannerSetting;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Recognises the club's own teams in free-form match names such as
 * "AETOS Plauti DS 1 - Orion DS 3". The club name prefixes come from
 * PlannerSetting::team_name_patterns (e.g. ["AETOS", "AET"]) and are matched
 * as whole words, case-insensitively. Without patterns no name is recognised.
 */
final class ClubTeamMatcher
{
    private const CATEGORY_CODES = 'HS|DS|JA|MA|JB|MB|JC|MC|H|D';

    /** @var WeakMap<Club, self>|null */
    private static ?WeakMap $cache = null;

    /** @var list<string> */
    private readonly array $patterns;

    /** @param  array<int, mixed>  $patterns */
    public function __construct(array $patterns)
    {
        $clean = array_values(array_unique(array_filter(
            array_map(static fn (mixed $pattern): string => trim((string) $pattern), $patterns),
            static fn (string $pattern): bool => $pattern !== '',
        )));

        // Longest first, so "AETOS" wins over "AET" in an alternation.
        usort($clean, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $this->patterns = $clean;
    }

    /** The matcher for the current club, cached per club instance until its settings are saved. */
    public static function forCurrentClub(): self
    {
        $club = app(CurrentClub::class)->require();

        if (isset(self::$cache[$club])) {
            return self::$cache[$club];
        }

        // Read before touching the cache: creating the settings row flushes it.
        $matcher = new self(PlannerSetting::current()->team_name_patterns ?? []);
        self::$cache ??= new WeakMap;

        return self::$cache[$club] = $matcher;
    }

    /** The matcher for a specific club, regardless of the current club. */
    public static function forClub(Club $club): self
    {
        return app(CurrentClub::class)->run($club, static fn (): self => self::forCurrentClub());
    }

    /** Forgets cached matchers; called whenever planner settings are saved. */
    public static function flush(): void
    {
        self::$cache = null;
    }

    /** The configured club name prefixes, longest first. @return list<string> */
    public function patterns(): array
    {
        return $this->patterns;
    }

    /** Whether any club name prefix is configured. */
    public function hasPatterns(): bool
    {
        return $this->patterns !== [];
    }

    /** Whether the name contains the club name anywhere, i.e. it is one of our teams ("AETOS Plauti DS 1"). */
    public function isOwnTeam(?string $name): bool
    {
        return $this->clubRegex() !== null && preg_match($this->clubRegex(), (string) $name) === 1;
    }

    /** Whether the name starts with the club name, e.g. to decide home or away from the home team column. */
    public function startsWithClubName(?string $name): bool
    {
        $regex = $this->clubRegex('^\s*');

        return $regex !== null && preg_match($regex, (string) $name) === 1;
    }

    /** Removes a leading club name ("AETOS HS 1" -> "HS 1"); other names are returned trimmed but unchanged. */
    public function stripClubPrefix(string $name): string
    {
        $regex = $this->clubRegex('^\s*', '\s*');

        return trim($regex === null ? $name : (preg_replace($regex, '', $name, 1) ?? $name));
    }

    /** The text after the first club name occurrence, or null when the name has no club name. */
    public function textAfterClubName(?string $name): ?string
    {
        $regex = $this->clubRegex();

        if ($regex === null || preg_match($regex, (string) $name, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return substr((string) $name, $match[0][1] + strlen($match[0][0]));
    }

    /**
     * Whether the name is our team with this code. Only the part after the
     * club name counts, so the opponent's code ("Rebelle HS 2 - AETOS HS 3")
     * or a sibling team in the same pool feed never matches.
     */
    public function isOwnTeamWithCode(?string $name, ?string $code): bool
    {
        $pattern = TeamCode::pattern($code);
        $after = $this->textAfterClubName($name);

        return $pattern !== null && $after !== null && preg_match($pattern, $after) === 1;
    }

    /** Whether the name starts with the club name directly followed by this code ("AETOS XZ 1" for "XZ 1"). */
    public function startsWithOwnTeam(?string $name, ?string $code): bool
    {
        $pattern = TeamCode::pattern($code);

        if ($pattern === null || !$this->startsWithClubName($name)) {
            return false;
        }

        return preg_match($pattern, $this->stripClubPrefix((string) $name), $match, PREG_OFFSET_CAPTURE) === 1
            && $match[0][1] === 0;
    }

    /**
     * The first category team code after the club name, normalised to e.g.
     * "HS1"; null when the name is not ours or carries no category code.
     */
    public function ownTeamCode(?string $name): ?string
    {
        $regex = $this->clubRegex('', '.*?\b('.self::CATEGORY_CODES.')\s*(\d+)\b');

        if ($regex === null || preg_match($regex, (string) $name, $match) !== 1) {
            return null;
        }

        return Str::upper($match[1]).$match[2];
    }

    private function clubRegex(string $before = '', string $after = ''): ?string
    {
        if ($this->patterns === []) {
            return null;
        }

        $alternatives = implode('|', array_map(
            static fn (string $pattern): string => preg_quote($pattern, '/'),
            $this->patterns,
        ));

        return '/'.$before.'(?<![\p{L}\p{N}])(?:'.$alternatives.')(?![\p{L}\p{N}])'.$after.'/iu';
    }
}
