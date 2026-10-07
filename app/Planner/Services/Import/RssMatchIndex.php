<?php

namespace App\Planner\Services\Import;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\TeamCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds the existing match a feed item refers to. The primary key is the
 * match code; a second index on date, time and teams catches the same match
 * under another code. The federation delivers codes in two notations, and
 * every time they did not line up the whole planning doubled.
 */
class RssMatchIndex
{
    public const SYNTHETIC_PREFIX = 'RSS-';

    /** @var Collection<string, FixtureMatch> */
    private Collection $byCode;

    /** @var Collection<string, FixtureMatch> */
    private Collection $byMatch;

    public function __construct(ScheduleVersion $version)
    {
        // On duplicate codes the first one wins, like the single query that
        // used to be here.
        $this->byCode = collect();
        foreach ($version->matches()->get() as $match) {
            $key = (string) $match->match_code;
            if ($key !== '' && !$this->byCode->has($key)) {
                $this->byCode->put($key, $match);
            }
        }

        $this->byMatch = collect();
        foreach ($this->byCode as $match) {
            $key = self::matchKey($match->date?->toDateString(), $match->time, $match->home_team_name, $match->away_team_name);
            if (!$this->byMatch->has($key)) {
                $this->byMatch->put($key, $match);
            }
        }
    }

    /**
     * The existing match for this feed item under the given code: by code,
     * by the legacy synthetic code (renumbered once), or by the match itself.
     *
     * @param  array<string, mixed>  $item
     */
    public function find(array $item, string $code): ?FixtureMatch
    {
        return $this->byCode->get($code)
            ?? ($item['code'] ? null : $this->renameLegacyCode(self::legacyCode($item), $code))
            ?? $this->sameMatch($item, $code);
    }

    /** @param  array<string, mixed>  $item */
    public function add(FixtureMatch $match, array $item, string $code): void
    {
        $this->byCode->put($code, $match);
        $this->byMatch->put(self::matchKey($item['date'], $item['time'], $item['home_team'], $item['away_team']), $match);
    }

    public static function isSynthetic(?string $code): bool
    {
        return str_starts_with((string) $code, self::SYNTHETIC_PREFIX);
    }

    /**
     * Feeds without a match code get one of their own. Date and time are not
     * part of it: when the federation moved such a match the code changed
     * along, the sync found nothing and created a duplicate.
     *
     * @param  array<string, mixed>  $item
     */
    public static function syntheticCode(array $item): string
    {
        return self::SYNTHETIC_PREFIX.strtoupper(substr(hash('sha256', implode('|', [
            TeamCode::key($item['home_team']), TeamCode::key($item['away_team']),
            Str::upper(trim((string) $item['pool'])),
        ])), 0, 16));
    }

    /**
     * The code as it was computed before that fix.
     *
     * @param  array<string, mixed>  $item
     */
    public static function legacyCode(array $item): string
    {
        return self::SYNTHETIC_PREFIX.strtoupper(substr(hash('sha256', implode('|', [
            $item['date'], $item['time'], $item['home_team'], $item['away_team'],
        ])), 0, 16));
    }

    public static function matchKey(?string $date, ?string $time, ?string $home, ?string $away): string
    {
        return implode('|', [$date, mb_substr((string) $time, 0, 5), TeamCode::key($home), TeamCode::key($away)]);
    }

    /**
     * Matches created before the fix still carry the old code. They are
     * renumbered once, so the transition creates no duplicates.
     */
    private function renameLegacyCode(string $legacyCode, string $code): ?FixtureMatch
    {
        $existing = $this->byCode->get($legacyCode);
        if (!$existing) {
            return null;
        }

        $existing->update(['match_code' => $code]);
        $this->byCode->forget($legacyCode);
        $this->byCode->put($code, $existing);

        return $existing;
    }

    /**
     * The same match under another code. Two teams cannot play each other
     * twice at the same moment, so this is the match the feed means; it takes
     * the source's code and continues as the existing row instead of a copy.
     *
     * @param  array<string, mixed>  $item
     */
    private function sameMatch(array $item, string $code): ?FixtureMatch
    {
        $existing = $this->byMatch->get(self::matchKey($item['date'], $item['time'], $item['home_team'], $item['away_team']));
        if (!$existing) {
            return null;
        }

        $this->byCode->forget((string) $existing->match_code);
        $existing->update(['match_code' => $code]);
        $this->byCode->put($code, $existing);

        return $existing;
    }
}
