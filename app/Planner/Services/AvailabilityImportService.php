<?php

namespace App\Planner\Services;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Court;
use App\Planner\Models\Venue;
use App\Planner\Support\VenueName;
use App\Planner\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads venue availability from a spreadsheet. The venue committee gets the
 * season's hall rental from the municipality; typing that over by hand is
 * work where mistakes creep in.
 *
 * Expected (Dutch) columns: sporthal, scope, datum or weekdag or periode
 * van/tot, status, begintijd, eindtijd, veld and notitie. Only sporthal,
 * scope and status are required; the rest depends on the scope. Scope and
 * status accept the Dutch words (datum/weekdag/periode, beschikbaar/
 * gedeeltelijk/gesloten) as well as the stored English values.
 */
class AvailabilityImportService
{
    private const SCOPES = [
        'datum' => AvailabilityScope::Date, 'weekdag' => AvailabilityScope::Weekday, 'periode' => AvailabilityScope::Period,
    ];

    private const STATUSES = [
        'beschikbaar' => AvailabilityStatus::Available, 'gedeeltelijk' => AvailabilityStatus::Partial,
        'gesloten' => AvailabilityStatus::Closed,
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /** @return array{valid: int, skipped: int, errors: list<array{row: int, reason: string}>, examples: list<array<string, mixed>>} */
    public function analyze(string $path): array
    {
        $result = $this->processRows($path);

        return [
            'valid' => count($result['rules']),
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'examples' => array_slice($result['rules'], 0, 10),
        ];
    }

    /** @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, reason: string}>} */
    public function import(string $path): array
    {
        $result = $this->processRows($path);

        // All or nothing, like the training import: half an import is worse
        // than none, because you cannot see which half is there.
        return DB::transaction(function () use ($result): array {
            $created = 0;
            $updated = 0;

            foreach ($result['rules'] as $rule) {
                $model = $this->existingRule($rule) ?? new Availability;
                $existed = $model->exists;
                $model->fill(Arr::except($rule, ['venue', 'court']))->save();
                $existed ? $updated++ : $created++;
            }

            return compact('created', 'updated') + ['skipped' => $result['skipped'], 'errors' => $result['errors']];
        });
    }

    /**
     * The rule this row would overwrite. Date columns go through whereDate:
     * with a date cast Laravel writes a full timestamp in sqlite, so equality
     * on the bare date finds nothing while MySQL does match it.
     *
     * @param  array<string, mixed>  $rule
     */
    private function existingRule(array $rule): ?Availability
    {
        $query = Availability::query()
            ->where('venue_id', $rule['venue_id'])
            ->where('scope', $rule['scope'])
            ->where(fn ($q) => $rule['court_id'] === null ? $q->whereNull('court_id') : $q->where('court_id', $rule['court_id']))
            ->where(fn ($q) => $rule['weekday'] === null ? $q->whereNull('weekday') : $q->where('weekday', $rule['weekday']));

        foreach (['date', 'period_start', 'period_end'] as $column) {
            $query->when(
                $rule[$column] === null,
                fn ($q) => $q->whereNull($column),
                fn ($q) => $q->whereDate($column, $rule[$column]),
            );
        }

        return $query->first();
    }

    /** @return array{rules: list<array<string, mixed>>, skipped: int, errors: list<array{row: int, reason: string}>} */
    private function processRows(string $path): array
    {
        $rules = [];
        $errors = [];
        $skipped = 0;

        $venues = Venue::query()->get();
        $courts = Court::query()->get();

        foreach ($this->reader->read($path) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = collect($rawRow)->mapWithKeys(fn ($value, $header) => [Str::lower(trim((string) $header)) => trim((string) $value)]);

            $venueName = $row->get('sporthal', '');
            if ($venueName === '') {
                $skipped++;

                continue;
            }

            $reasons = [];
            $venue = $this->findVenue($venues, $venueName);
            if (!$venue) {
                $reasons[] = __('Onbekende sporthal: :name', ['name' => $venueName]);
            }

            $scope = $this->scope($row->get('scope', ''));
            if (!$scope) {
                $reasons[] = __('Scope moet datum, weekdag of periode zijn');
            }

            $rawStatus = $row->get('status', '');
            $status = $rawStatus === '' ? AvailabilityStatus::Available : $this->status($rawStatus);
            if (!$status) {
                $reasons[] = __('Status moet beschikbaar, gedeeltelijk of gesloten zijn');
            }

            $date = $this->date($row->get('datum', ''));
            $weekday = $this->weekday($row->get('weekdag', ''));
            $periodStart = $this->date($row->get('periode van', '') ?: $row->get('periode_start', ''));
            $periodEnd = $this->date($row->get('periode tot', '') ?: $row->get('periode_eind', ''));

            match ($scope) {
                AvailabilityScope::Date => $date ?: $reasons[] = __('Datum is verplicht bij scope datum'),
                AvailabilityScope::Weekday => $weekday !== null ?: $reasons[] = __('Weekdag is verplicht bij scope weekdag'),
                AvailabilityScope::Period => ($periodStart && $periodEnd) ?: $reasons[] = __('Periode van en tot zijn verplicht bij scope periode'),
                default => null,
            };

            $start = $this->time($rawStart = $row->get('begintijd', ''));
            $end = $this->time($rawEnd = $row->get('eindtijd', ''));
            foreach (['begintijd' => [$rawStart, $start], 'eindtijd' => [$rawEnd, $end]] as $column => [$raw, $read]) {
                if ($raw !== '' && $read === null) {
                    $reasons[] = __('Onleesbare :column: :value', ['column' => $column, 'value' => $raw]);
                }
            }
            if (($start === null) !== ($end === null)) {
                $reasons[] = __('Vul begintijd en eindtijd allebei in, of allebei niet');
            }
            if ($start && $end && $end <= $start) {
                $reasons[] = __('Eindtijd moet na begintijd liggen');
            }

            $courtName = $row->get('veld', '');
            $court = null;
            if ($courtName !== '' && $venue) {
                $court = $courts->first(fn (Court $c): bool => $c->venue_id === $venue->id
                    && Str::lower(trim($c->name)) === Str::lower($courtName));
                if (!$court) {
                    $reasons[] = __('Onbekend veld in :venue: :court', ['venue' => $venueName, 'court' => $courtName]);
                }
            }

            if ($reasons !== []) {
                $errors[] = ['row' => $rowNumber, 'reason' => implode('; ', $reasons)];

                continue;
            }

            $rules[] = [
                'venue_id' => $venue->id,
                'venue' => $venue->name,
                'court_id' => $court?->id,
                'court' => $court?->name,
                'scope' => $scope->value,
                'date' => $scope === AvailabilityScope::Date ? $date : null,
                'weekday' => $scope === AvailabilityScope::Weekday ? $weekday : null,
                'period_start' => $scope === AvailabilityScope::Period ? $periodStart : null,
                'period_end' => $scope === AvailabilityScope::Period ? $periodEnd : null,
                'start_time' => $start,
                'end_time' => $end,
                'status' => $status->value,
                'note' => $row->get('notitie') ?: null,
            ];
        }

        return ['rules' => $rules, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function scope(string $value): ?AvailabilityScope
    {
        $value = Str::lower($value);

        return self::SCOPES[$value] ?? AvailabilityScope::tryFrom($value);
    }

    private function status(string $value): ?AvailabilityStatus
    {
        $value = Str::lower($value);

        return self::STATUSES[$value] ?? AvailabilityStatus::tryFrom($value);
    }

    /** @param  Collection<int, Venue>  $venues */
    private function findVenue(Collection $venues, string $name): ?Venue
    {
        $key = VenueName::key($name);

        return $venues->first(fn (Venue $venue): bool => VenueName::key($venue->name) === $key);
    }

    private function date(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        // In an xlsx a date cell is not text but the day number since
        // 30-12-1899: typing 12-09-2026 in Excel delivers 46277 here.
        if (is_numeric($value)) {
            $days = (float) str_replace(',', '.', $value);

            // The lower bound 20000 (1954) keeps out a cell holding only a
            // year, which would otherwise silently become a date a century ago.
            return $days >= 20000 && $days < 60000
                ? CarbonImmutable::create(1899, 12, 30)->addDays((int) $days)->toDateString()
                : null;
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $value)->toDateString();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /** The source may use a (Dutch) name or a number; 0 is Sunday, as in the rules themselves. */
    private function weekday(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (int) $value;

            return $number >= 0 && $number <= 6 ? $number : null;
        }

        $position = array_search(Str::lower($value), Weekday::NAMES, true);

        return $position === false ? null : ($position + 1) % 7;
    }

    /**
     * A time arrives in two shapes. From an xlsx it is a fraction of a day --
     * 19:00 is 0.791666 -- and from a csv usually "19:00" or "19.30". The old
     * regex read that fraction as zero hours and 79 minutes, after which
     * MySQL aborted the whole import with "Incorrect time value: '00:79'".
     */
    private function time(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $value, $match) === 1) {
            [$hour, $minute] = [(int) $match[1], (int) $match[2]];
            if ($hour <= 23 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        if (is_numeric($value)) {
            // Only the fraction counts: a cell with date and time is stored
            // as 46000.791666.
            $minutes = (int) round(fmod((float) str_replace(',', '.', $value), 1) * 1440);

            return $minutes >= 0 && $minutes < 1440
                ? sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60)
                : null;
        }

        return null;
    }
}
