<?php

namespace App\Planner\Services\Duties;

use App\Planner\Support\TeamCode;

/**
 * The club-wide settings of the duty planning, from planner_settings.duty_rules.
 * Who referees, scores or does hall duty lives on the team (its planning
 * exceptions); what stays here applies to the whole club: which home teams
 * count as heavy or restricted, the evening limit for scoring and the home
 * city. Missing keys fall back to DEFAULTS.
 */
final class DutyRules
{
    public const DEFAULTS = [
        // Words that mark a lower class ("women 3rd/4th class").
        'lower_class_terms' => ['3e', '4e', 'derde', 'vierde'],
        // From this start time on, teams with "no scoring late" do not score.
        'scorer_cutoff' => '19:00',
        // An away match in this city counts as a match in the own hall. Empty
        // means: the city of the main venue.
        'home_city' => '',
        // Home teams only teams with "referees restricted teams" referee, e.g. ['HS 2', 'HS 3'].
        'restricted_referee_teams' => [],
        // Home teams that teams with "no scoring at heavy teams" do not score, e.g. ['HS 1', 'DS 1'].
        'scorer_heavy_teams' => [],
    ];

    /** @var array<string, mixed> */
    private readonly array $values;

    /** @param  array<string, mixed>|null  $rules  planner_settings.duty_rules */
    public function __construct(?array $rules = null)
    {
        // Only missing keys fall back to the default: a club that empties a
        // list means "none", not "give me the default back".
        $given = array_filter(
            $rules ?? [],
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $this->values = array_intersect_key(array_replace(self::DEFAULTS, $given), self::DEFAULTS);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** Recognises the lower classes ("women 3rd/4th class"). */
    public function isLowerClass(?string $text): bool
    {
        $terms = array_filter(array_map('strval', (array) $this->values['lower_class_terms']));
        if ($terms === [] || ($text ?? '') === '') {
            return false;
        }

        $pattern = implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms));

        return preg_match('/(?<![A-Z0-9])('.$pattern.')(?![A-Z0-9])/i', (string) $text) === 1;
    }

    public function scorerCutoff(): string
    {
        return substr((string) $this->values['scorer_cutoff'], 0, 5) ?: '19:00';
    }

    public function scorerCutoffMinutes(): int
    {
        return PlayingRounds::minutes($this->scorerCutoff());
    }

    public function homeCity(): string
    {
        return trim((string) $this->values['home_city']);
    }

    /** @return list<string> */
    public function restrictedRefereeTeams(): array
    {
        return $this->codes('restricted_referee_teams');
    }

    /** @return list<string> */
    public function scorerHeavyTeams(): array
    {
        return $this->codes('scorer_heavy_teams');
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
