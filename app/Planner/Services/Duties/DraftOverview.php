<?php

namespace App\Planner\Services\Duties;

use Illuminate\Support\Collection;

/**
 * Turns a duty draft (DutyPlanner::generate()) into one chronological list per
 * playing day and venue, with the hall duties in between the matches instead
 * of in a separate list.
 */
class DraftOverview
{
    public const TYPE_MATCH = 'match';

    public const TYPE_HALL_DUTY = 'hall_duty';

    /**
     * @param  array  $validation  DutyPlanner::validate() result
     * @param  ?int  $mainVenueId  the venue that comes first per playing day
     * @return list<array{date: ?string, venue_id: ?int, venue: string, counts: array{matches: int, referee: int, scorer: int, hall_duties: int, issues: int}, rows: list<array<string, mixed>>}>
     */
    public function blocks(array $draft, array $validation = [], ?int $mainVenueId = null): array
    {
        $rows = collect();
        $unknownVenue = __('Onbekende sporthal');

        foreach ($draft['matches'] ?? [] as $index => $match) {
            $errors = $validation['matches'][$match['id'] ?? null] ?? [];
            $rows->push([
                'key' => $this->key($match),
                'date' => $match['date'] ?? null,
                'venue_id' => $match['venue_id'] ?? null,
                'venue' => $match['venue'] ?? $unknownVenue,
                'type' => self::TYPE_MATCH,
                'index' => $index,
                'id' => $match['id'] ?? null,
                'sort_time' => substr((string) ($match['time'] ?? ''), 0, 5),
                'time' => substr((string) ($match['time'] ?? ''), 0, 5),
                'court' => $match['court'] ?? null,
                'code' => $match['code'] ?? null,
                'home' => $match['home'] ?? null,
                'away' => $match['away'] ?? null,
                'referee' => $this->field(
                    $match['referee'] ?? null,
                    $match['suggested_referee'] ?? null,
                    (bool) ($match['locked']['referee'] ?? false),
                    $errors['referee'] ?? [],
                    nevoboPossible: true,
                ),
                'scorer' => $this->field(
                    $match['scorer'] ?? null,
                    $match['suggested_scorer'] ?? null,
                    (bool) ($match['locked']['scorer'] ?? false),
                    $errors['scorer'] ?? [],
                ),
            ]);
        }

        foreach ($draft['hall_duties'] ?? [] as $index => $duty) {
            $rows->push([
                'key' => $this->key($duty),
                'date' => $duty['date'] ?? null,
                'venue_id' => $duty['venue_id'] ?? null,
                'venue' => $duty['venue'] ?? $unknownVenue,
                'type' => self::TYPE_HALL_DUTY,
                'index' => $index,
                'sort_time' => $duty['start'] ?? '',
                'time' => ($duty['start'] ?? '').'–'.($duty['end'] ?? ''),
                'team' => $this->field(
                    $duty['team_code'] ?? null,
                    $duty['suggested_team'] ?? ($duty['team_code'] ?? null),
                    (bool) ($duty['locked'] ?? false),
                    $validation['hall_duties'][$index] ?? [],
                ),
            ]);
        }

        // The main venue comes first per playing day; the rest follows by name.
        return $rows
            ->groupBy('key')
            ->map(fn (Collection $group): array => $this->block($group))
            ->sortBy(fn (array $block): string => sprintf(
                '%s|%d|%s',
                $block['date'],
                $mainVenueId !== null && $block['venue_id'] === $mainVenueId ? 0 : 1,
                $block['venue'],
            ))
            ->values()
            ->all();
    }

    private function block(Collection $group): array
    {
        $first = $group->first();
        // Hall duty before the matches of the same time: the duty starts
        // earlier and belongs above the matches it goes with.
        $rows = $group
            ->sortBy(fn (array $row): string => sprintf(
                '%s|%d|%s',
                $row['sort_time'],
                $row['type'] === self::TYPE_HALL_DUTY ? 0 : 1,
                $row['court'] ?? '',
            ))
            ->values();
        $matches = $rows->where('type', self::TYPE_MATCH);
        $duties = $rows->where('type', self::TYPE_HALL_DUTY);

        return [
            'date' => $first['date'],
            'venue_id' => $first['venue_id'],
            'venue' => $first['venue'],
            'counts' => [
                'matches' => $matches->count(),
                'referee' => $matches->filter(fn (array $row): bool => filled($row['referee']['value']))->count(),
                'scorer' => $matches->filter(fn (array $row): bool => filled($row['scorer']['value']))->count(),
                'hall_duties' => $duties->filter(fn (array $row): bool => filled($row['team']['value']))->count(),
                'issues' => $this->issues($rows),
            ],
            'rows' => $rows->map(fn (array $row): array => collect($row)->except('key')->all())->all(),
        ];
    }

    private function issues(Collection $rows): int
    {
        return $rows->sum(fn (array $row): int => match ($row['type']) {
            self::TYPE_HALL_DUTY => $row['team']['errors'] === [] ? 0 : 1,
            default => ($row['referee']['errors'] === [] ? 0 : 1) + ($row['scorer']['errors'] === [] ? 0 : 1),
        });
    }

    /** @return array{value: ?string, locked: bool, errors: list<string>, warnings: list<string>, origin: string} */
    private function field(?string $value, ?string $suggestion, bool $locked, array $errors, bool $nevoboPossible = false): array
    {
        // A warning belongs on screen, but does not count as an issue: the
        // planning committee may deliberately overrule such a rule.
        [$warnings, $realErrors] = collect($errors)->partition(fn (string $reason): bool => RuleEngine::isWarning($reason));

        return [
            'value' => $value,
            'locked' => $locked,
            'errors' => $realErrors->values()->all(),
            'warnings' => $warnings->values()->all(),
            'origin' => $this->origin($value, $suggestion, $locked, $nevoboPossible),
        ];
    }

    private function origin(?string $value, ?string $suggestion, bool $locked, bool $nevoboPossible): string
    {
        return match (true) {
            blank($value) => __('Nog niet ingevuld.'),
            $locked => __('Vastgezet: blijft staan bij opnieuw genereren.'),
            $nevoboPossible && strcasecmp($value, DutyPlanner::NEVOBO) === 0 => __('De bond wijst een scheidsrechter aan.'),
            $value === $suggestion => __('Automatisch voorgesteld door de planner.'),
            default => __('Handmatig gekozen.'),
        };
    }

    private function key(array $row): string
    {
        return ($row['date'] ?? '').'|'.($row['venue_id'] ?? '');
    }
}
