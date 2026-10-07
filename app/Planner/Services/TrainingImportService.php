<?php

namespace App\Planner\Services;

use App\Planner\Enums\Source;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports the training schedule of the current club from a spreadsheet with
 * the (Dutch) columns Sporthal, Dag, Begintijd, Eindtijd, Type veld, Veld and
 * Team. Requires the trainings module.
 */
class TrainingImportService
{
    public function __construct(private readonly SpreadsheetReader $reader) {}

    /** @return array{valid: int, skipped: int, errors: list<array{row: int, reason: string}>, examples: list<array<string, mixed>>} */
    public function analyze(string $path): array
    {
        $result = $this->processRows($path);

        return [
            'valid' => count($result['trainings']),
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'examples' => array_slice($result['trainings'], 0, 10),
        ];
    }

    /** @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, reason: string}>} */
    public function import(string $path, ?int $userId = null): array
    {
        $result = $this->processRows($path);

        // All or nothing: without a transaction an import that tripped
        // halfway stayed in the database as half the truth.
        return DB::transaction(fn (): array => $this->store($result, $userId));
    }

    /** @return array{created: int, updated: int, skipped: int, errors: list<array{row: int, reason: string}>} */
    private function store(array $result, ?int $userId): array
    {
        $created = 0;
        $updated = 0;

        foreach ($result['trainings'] as $training) {
            // The court belongs in the key: a team trains on several courts
            // at once, and two free courts at the same time are two rows.
            // Without the court they overwrote each other. This is the same
            // key as the unique index on the table.
            $model = Training::query()->firstOrNew([
                'team_id' => $training['team_id'],
                'weekday' => $training['weekday'],
                'start_time' => $training['start_time'],
                'venue_id' => $training['venue_id'],
                'court' => $training['court'],
            ]);
            $existed = $model->exists;
            $model->fill(Arr::except($training, ['venue', 'team']) + [
                'source' => Source::Import,
                'created_by' => $model->created_by ?: $userId,
            ])->save();
            $existed ? $updated++ : $created++;
        }

        return compact('created', 'updated') + ['skipped' => $result['skipped'], 'errors' => $result['errors']];
    }

    /** @return array{trainings: list<array<string, mixed>>, skipped: int, errors: list<array{row: int, reason: string}>} */
    private function processRows(string $path): array
    {
        if (!app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Trainings)) {
            throw new RuntimeException(__('De module trainingen staat niet aan voor deze club.'));
        }

        $trainings = [];
        $errors = [];
        $skipped = 0;
        // Load all teams once, keyed by team code key: a query per row cost
        // 126 queries for a schedule of 126 rows, and comparing the raw code
        // missed every team whose code has a space.
        $teams = Team::query()->get();
        $byCode = $teams->keyBy(fn (Team $team): string => TeamCode::key($team->code));
        $byName = $teams->keyBy(fn (Team $team): string => Str::lower(trim($team->name)));

        foreach ($this->reader->read($path) as $index => $rawRow) {
            $rowNumber = $index + 2;
            $row = collect($rawRow)->mapWithKeys(fn ($value, $header) => [Str::lower(trim((string) $header)) => trim((string) $value)]);
            $venueName = $row->get('sporthal', '');
            $teamName = $this->normalizeTeam($row->get('team', ''));
            $weekday = Str::lower($row->get('dag', ''));
            $start = $this->normalizeTime($row->get('begintijd', ''));
            $end = $this->normalizeTime($row->get('eindtijd', ''));
            $court = filter_var($row->get('veld'), FILTER_VALIDATE_INT);
            $reasons = [];

            $venue = $this->findVenue($venueName);
            if (!$venue) {
                $reasons[] = $venueName === '' ? __('Sporthal is verplicht') : __('Onbekende sporthal: :name', ['name' => $venueName]);
            }
            if (!in_array($weekday, Training::WEEKDAYS, true)) {
                $reasons[] = __('Dag is ongeldig');
            }
            if (!$start) {
                $reasons[] = __('Begintijd is ongeldig');
            }
            if (!$end) {
                $reasons[] = __('Eindtijd is ongeldig');
            }
            if ($start && $end && $end <= $start) {
                $reasons[] = __('Eindtijd moet na begintijd liggen');
            }
            if ($court === false || $court < 1 || $court > 6) {
                $reasons[] = __('Veld moet een getal van 1 t/m 6 zijn');
            }
            // A row without a team is free hall time: the court is occupied,
            // just not assigned yet. It belongs in the planning, otherwise the
            // committee plans a match over it. An unknown name is an error.
            $team = null;
            if ($teamName !== '') {
                $team = $byCode->get(TeamCode::key($teamName)) ?? $byName->get(Str::lower($teamName));
                if (!$team) {
                    $reasons[] = __('Onbekend team: :name', ['name' => $teamName]);
                }
            }

            if ($reasons) {
                $errors[] = ['row' => $rowNumber, 'reason' => implode('; ', $reasons)];

                continue;
            }

            $trainings[] = [
                'team_id' => $team?->id,
                'venue_id' => $venue->id,
                'venue' => $venue->name,
                'team' => $team?->name,
                'weekday' => $weekday,
                'start_time' => $start,
                'end_time' => $end,
                'court' => $court,
                'court_type' => $row->get('type veld') ?: null,
            ];
        }

        return compact('trainings', 'skipped', 'errors');
    }

    private function findVenue(string $name): ?Venue
    {
        $normalized = Str::lower(trim($name));
        if ($normalized === '') {
            return null;
        }

        $venue = Venue::query()->whereRaw('LOWER(name) = ?', [$normalized])->first();
        if ($venue) {
            return $venue;
        }

        return VenueAlias::query()
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->with('venue')
            ->first()?->venue;
    }

    private function normalizeTeam(string $team): string
    {
        $compact = Str::upper(preg_replace('/\s+/', '', trim($team)) ?? '');
        if (preg_match('/^(D|H)(\d+)$/', $compact, $match)) {
            return ($match[1] === 'D' ? 'DS ' : 'HS ').$match[2];
        }
        if (preg_match('/^([A-Z]{2})(\d+)$/', $compact, $match)) {
            return $match[1].' '.$match[2];
        }

        return Str::upper(trim($team));
    }

    private function normalizeTime(string $time): ?string
    {
        $time = trim($time);
        if (is_numeric($time) && (float) $time >= 0 && (float) $time < 1) {
            $seconds = (int) round((float) $time * 86400);

            return sprintf('%02d:%02d:00', intdiv($seconds, 3600) % 24, intdiv($seconds % 3600, 60));
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $match)) {
            return null;
        }
        if ((int) $match[1] > 23 || (int) $match[2] > 59 || (int) ($match[3] ?? 0) > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $match[1], $match[2], $match[3] ?? 0);
    }
}
