<?php

namespace App\Planner\Services\Duties;

use App\Planner\Support\TeamCode;

/**
 * The club's duty rules (referee, scorer, hall duty): which teams never
 * referee, which home teams may only be refereed by certain teams, and so on.
 * Team line-ups change every season, so these rules live in
 * planner_settings.duty_rules instead of in code. This class reads them and
 * falls back to DEFAULTS for missing keys.
 *
 * The team-specific defaults are empty: a club without configuration gets no
 * team-specific restrictions, only the generic youth/category rules.
 */
final class DutyRules
{
    public const DEFAULTS = [
        // Teams that never referee, e.g. ['DS 10'].
        'no_referee' => [],
        // Home team code => teams allowed to referee it, e.g. ['HS 2' => ['DS 1', 'HS 1']].
        'referee_requirements' => [],
        // Words that mark a lower class, where A youth and lower women's teams may referee.
        'lower_class_terms' => ['3e', '4e', 'derde', 'vierde'],
        // Home teams C youth may not score for, e.g. ['HS 1', 'DS 1'].
        'scorer_heavy_teams' => [],
        // From this time on C youth no longer scores.
        'scorer_cutoff' => '19:00',
        // Team code prefixes that may do hall duty.
        'hall_duty_codes' => ['HS', 'DS', 'H', 'D', 'JA', 'MA'],
        // Maximum duties per team per playing day; 0 means no limit.
        'max_duties_per_day' => 3,
    ];

    /** @var array<string, mixed> */
    private readonly array $values;

    /** @param  array<string, mixed>|null  $rules  planner_settings.duty_rules */
    public function __construct(?array $rules = null)
    {
        // Only missing keys fall back to the default: a club that empties a
        // list means "nobody", not "give me the default back".
        $given = array_filter(
            $rules ?? [],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $this->values = array_replace(self::DEFAULTS, $given);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** A team that according to the club never has to referee. */
    public function mayNotReferee(string $code): bool
    {
        return in_array($code, $this->codes('no_referee'), true);
    }

    /**
     * The teams allowed to referee this home team, or null when no special
     * requirement applies to it.
     *
     * @return list<string>|null
     */
    public function refereeRequirement(string $homeCode): ?array
    {
        if ($homeCode === '') {
            return null;
        }

        foreach ((array) $this->values['referee_requirements'] as $team => $allowed) {
            if (TeamCode::key((string) $team) === $homeCode) {
                return array_values(array_filter(array_map(
                    static fn (mixed $code): string => TeamCode::key((string) $code),
                    (array) $allowed,
                )));
            }
        }

        return null;
    }

    /** Recognises the lower classes where A youth and lower women's teams may referee. */
    public function isLowerClass(?string $text): bool
    {
        $terms = array_filter(array_map('strval', (array) $this->values['lower_class_terms']));
        if ($terms === [] || ($text ?? '') === '') {
            return false;
        }

        $pattern = implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms));

        return preg_match('/(?<![A-Z0-9])('.$pattern.')(?![A-Z0-9])/i', (string) $text) === 1;
    }

    /** @return list<string> */
    public function scorerHeavyTeams(): array
    {
        return $this->codes('scorer_heavy_teams');
    }

    public function scorerCutoff(): string
    {
        return substr((string) $this->values['scorer_cutoff'], 0, 5) ?: '19:00';
    }

    public function scorerCutoffMinutes(): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $this->scorerCutoff())), 2, 0);

        return $hour * 60 + $minute;
    }

    /**
     * How many duties the generator gives a team on one playing day at most.
     * Without this limit a team with few volunteer points tops the candidate
     * list every time and gets an entire Saturday in a row. Zero means no
     * limit.
     */
    public function maxDutiesPerDay(): int
    {
        return max(0, (int) $this->values['max_duties_per_day']);
    }

    public function mayDoHallDuty(string $code): bool
    {
        $prefixes = array_filter(array_map(
            static fn (mixed $prefix): string => TeamCode::key((string) $prefix),
            (array) $this->values['hall_duty_codes'],
        ));

        foreach ($prefixes as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** "DS10" reads as DS 10 once it ends up in a message. */
    public static function readable(string $code): string
    {
        return preg_replace('/^([A-Z]+)(\d+)$/', '$1 $2', $code) ?: $code;
    }

    /** "DS 1, HS 1 of HS 3". @param list<string> $codes */
    public static function enumerate(array $codes): string
    {
        $readable = array_map(self::readable(...), $codes);
        if (count($readable) < 2) {
            return implode('', $readable);
        }
        $last = array_pop($readable);

        return implode(', ', $readable).' '.__('of').' '.$last;
    }

    /** @return list<string> */
    private function codes(string $key): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $code): string => TeamCode::key((string) $code),
            (array) $this->values[$key],
        )));
    }
}
