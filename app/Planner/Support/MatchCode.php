<?php

namespace App\Planner\Support;

use Illuminate\Support\Str;

/**
 * The Nevobo RSS puts the match code in a fixed-width field: four digits
 * district number, six characters pool code padded right with spaces, then two
 * characters for the fixture. The federation prints the same match as pool,
 * dash, fixture, and that is how the planning file has it:
 *
 *   "50003CD   CH"    ->  "3CD-CH"
 *   "5000JBHA1 BA"    ->  "JBHA1-BA"
 *   "5000BD1A03BD"    ->  "BD1A03-BD"
 *
 * When two teams meet more than once in a pool, the feed numbers the repeat
 * while the planning file numbers the fixture itself (the first one without a
 * number), hence the plus one:
 *
 *   feed "5000JBHA1 BA"    = planning file "JBHA1-BA"     (1st fixture)
 *   feed "5000JBHA1 BA-1"  = planning file "JBHA1-BA-2"   (2nd fixture)
 *
 * Without this translation the nightly sync did not recognise a match and
 * created a duplicate.
 */
final class MatchCode
{
    /** Codes the import invents itself carry this prefix. */
    private const SYNTHETIC = 'RSS-';

    /** The district number every feed code starts with. */
    private const DISTRICT = '5000';

    /**
     * Brings the feed format back to the federation notation. Anything that
     * does not look like a feed code is returned untouched, except that
     * whitespace collapses into a dash as the import always did.
     */
    public static function normalize(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (Str::startsWith(Str::upper($raw), self::SYNTHETIC)) {
            return $raw;
        }

        // Four digits district, a pool field of exactly six characters, then
        // the fixture: two characters, with "-1" etc. for a repeat. Anything
        // else is a code somebody typed in.
        if (preg_match('/^\d{4}(.{6})(.+)$/', $raw, $parts)) {
            $pool = rtrim($parts[1]);
            $fixture = trim($parts[2]);

            if (preg_match('/^([A-Z0-9]{2})(?:-(\d+))?$/i', $fixture, $count) && preg_match('/^[A-Z0-9]+$/i', $pool)) {
                return Str::upper($pool.'-'.$count[1].self::fixtureNumber($count[2] ?? null));
            }
        }

        return preg_replace('/\s+/', '-', $raw) ?? $raw;
    }

    /**
     * Converts the feed's repeat number to the planning file's fixture number:
     * the repeat the feed counts as 1 is the 2nd fixture there. A fixture
     * without a number is the first and keeps no suffix.
     */
    private static function fixtureNumber(?string $repeat): string
    {
        return $repeat === null ? '' : '-'.((int) $repeat + 1);
    }

    /**
     * Repairs codes that an earlier sync stored with the district number still
     * in front. Two forms, because that code replaced the padding with a dash:
     *
     *   "5000BD1E16BC"    ->  "BD1E16-BC"
     *   "50003CD-AD"      ->  "3CD-AD"
     *   "5000JBHA1-BA-1"  ->  "JBHA1-BA-2"
     *
     * Returns null when the outcome is ambiguous; guessing is more dangerous
     * than leaving the row and reporting it.
     */
    public static function repairStored(?string $value): ?string
    {
        $raw = trim((string) $value);

        if (!Str::startsWith($raw, self::DISTRICT)) {
            return null;
        }

        $viaFeed = self::normalize($raw);

        if ($viaFeed !== null && !Str::startsWith($viaFeed, self::DISTRICT) && self::isFederationNotation($viaFeed)) {
            return $viaFeed;
        }

        $rest = mb_substr($raw, mb_strlen(self::DISTRICT));

        if (!self::isFederationNotation($rest)) {
            return null;
        }

        // This form came from the old code replacing the padding; the number
        // still comes from the feed and has to be converted.
        return Str::upper(preg_replace_callback(
            '/-(\d+)$/',
            static fn (array $count): string => self::fixtureNumber($count[1]),
            $rest,
        ) ?? $rest);
    }

    /** Pool code, dash, fixture, and for a repeat a number. */
    private static function isFederationNotation(string $value): bool
    {
        return (bool) preg_match('/^[A-Z0-9]+-[A-Z0-9]{2}(?:-\d+)?$/i', $value);
    }

    /** Key to compare two codes, whether they come from the feed or the planning file. */
    public static function key(?string $value): string
    {
        return Str::upper(preg_replace('/[^A-Z0-9]/i', '', (string) self::normalize($value)) ?? '');
    }
}
