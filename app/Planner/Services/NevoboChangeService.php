<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeField;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\NevoboChange;
use App\Planner\Models\ScheduleVersion;

/**
 * Lists where the planning of home matches deviates from what Nevobo has, so
 * the planning committee can enter those changes at Nevobo and tick them off.
 */
class NevoboChangeService
{
    /**
     * @return list<array{match_id: int, version_id: int, code: ?string, date: ?string, time: ?string, home_team: ?string, away_team: ?string, venue: ?string, fields: list<array{field: string, old: ?string, new: ?string, processed: bool}>}>
     */
    public function forVersion(ScheduleVersion $version): array
    {
        $processed = NevoboChange::query()->where('version_id', $version->id)->get()
            ->keyBy(fn (NevoboChange $change): string => $change->match_id.':'.$change->field->value);

        return $version->matches()->where('match_type', MatchType::Home)
            ->with(['venue.aliases', 'court'])->orderBy('date')->orderBy('time')->get()
            ->map(function (FixtureMatch $match) use ($processed, $version): ?array {
                $values = [
                    NevoboChangeField::Date->value => [$match->original_date?->toDateString(), $match->date?->toDateString()],
                    NevoboChangeField::Time->value => [FixtureMatch::shortTime($match->original_time), FixtureMatch::shortTime($match->time)],
                    NevoboChangeField::Venue->value => $match->isInNevoboVenue()
                        ? [null, null]
                        : [$match->original_venue, $match->venue?->name],
                    NevoboChangeField::Court->value => [$match->original_court, $match->court?->name],
                ];
                $fields = collect($values)->filter(fn (array $pair): bool => ($pair[0] ?? null) !== ($pair[1] ?? null))
                    ->map(fn (array $pair, string $field): array => [
                        'field' => $field, 'old' => $pair[0], 'new' => $pair[1],
                        'processed' => (bool) $processed->get($match->id.':'.$field)?->is_processed,
                    ])->values()->all();
                if ($fields === []) {
                    return null;
                }

                return [
                    'match_id' => $match->id, 'version_id' => $version->id,
                    'code' => $match->match_code, 'date' => $match->date?->toDateString(),
                    'time' => FixtureMatch::shortTime($match->time), 'home_team' => $match->home_team_name,
                    'away_team' => $match->away_team_name, 'venue' => $match->venue?->name,
                    'fields' => $fields,
                ];
            })->filter()->values()->all();
    }

    /**
     * At Nevobo the changes of a match are entered in one go, not per field.
     * Ticking field by field cost needless clicks and gave an in-between state
     * that does not exist in practice.
     *
     * @return int the number of fields updated
     */
    public function markAll(FixtureMatch $match, bool $processed, ?int $userId = null): int
    {
        $change = collect($this->forVersion($match->version))->firstWhere('match_id', $match->id);
        $fields = collect($change['fields'] ?? []);

        foreach ($fields as $field) {
            $this->mark($match, NevoboChangeField::from($field['field']), $processed, $userId);
        }

        return $fields->count();
    }

    public function mark(FixtureMatch $match, NevoboChangeField $field, bool $processed, ?int $userId = null): NevoboChange
    {
        $change = collect($this->forVersion($match->version))->firstWhere('match_id', $match->id);
        $details = collect($change['fields'] ?? [])->firstWhere('field', $field->value);
        $record = NevoboChange::updateOrCreate(
            ['match_id' => $match->id, 'field' => $field],
            [
                'version_id' => $match->version_id,
                'old_value' => $details['old'] ?? null, 'new_value' => $details['new'] ?? null,
                'is_processed' => $processed, 'processed_at' => $processed ? now() : null,
                'processed_by' => $processed ? $userId : null,
            ],
        );
        activity('planner-nevobo')->performedOn($match)
            ->withProperties(['field' => $field->value, 'processed' => $processed])->log('Nevobo-verwerking gewijzigd');

        return $record;
    }
}
