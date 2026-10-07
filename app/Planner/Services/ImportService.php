<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\Import\ImportRows;
use App\Planner\Services\Import\ImportVenues;
use App\Planner\Services\Import\TeamDefaults;
use App\Planner\Support\CellValue;
use App\Planner\Support\ClubTeamMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports the Nevobo planning export (Excel/CSV) of the current club: as a new
 * draft version, or as an update of an existing draft where every match is
 * categorised against what the planner already did with it.
 */
class ImportService
{
    public const CATEGORY_NEW = 'new';

    public const CATEGORY_UNCHANGED = 'unchanged';

    public const CATEGORY_AUTOSYNC = 'autosync';

    public const CATEGORY_SOURCE_UPDATE = 'source_update';

    public const CATEGORY_CONFLICT = 'conflict';

    public const CATEGORY_REMOVED = 'removed';

    /** Choices per match id for updateVersion(). */
    public const CHOICE_SOURCE = 'source';

    public const CHOICE_MANUAL = 'manual';

    public const CHOICE_KEEP = 'keep';

    public const CHOICE_DELETE = 'delete';

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly ConflictEngine $conflicts,
        private readonly SittingVolleyballService $sittingVolleyball,
        private readonly ImportRows $rows,
        private readonly ImportVenues $venues,
        private readonly TeamDefaults $teamDefaults,
    ) {}

    /**
     * Reads and checks the file without writing anything.
     *
     * @return array{rows: list<array<string, mixed>>, usable: int, skipped: list<array{row: int, reason: string}>, past: list<array<string, mixed>>, new_venues: list<string>, unknown_teams: list<string>, name_variants: list<array{source: string, venue: string, method: string}>, categories: array<string, list<mixed>>}
     */
    public function dryRun(string $path, ?ScheduleVersion $existing = null): array
    {
        $raw = $this->reader->read($path);
        if ($raw === []) {
            throw new InvalidArgumentException(__('Het importbestand bevat geen gegevens.'));
        }
        $missing = array_values(array_diff(ImportRows::REQUIRED, array_keys($raw[0])));
        if ($missing) {
            throw new InvalidArgumentException(__('Verplichte kolommen ontbreken: :columns', ['columns' => implode(', ', $missing)]));
        }

        $usable = [];
        $past = [];
        $skipped = [];
        foreach ($raw as $number => $row) {
            $reason = $this->rows->invalidReason($row);
            if ($reason) {
                $skipped[] = ['row' => $number + 2, 'reason' => $reason];

                continue;
            }
            $normalized = $this->rows->normalize($row);
            // The federation leaves played matches out of the file. They must
            // not come back as new rows, nor count as errors: the file is
            // right, the match has simply been played.
            if ($this->rows->isPlayed($normalized['date'])) {
                $past[] = $normalized;

                continue;
            }
            $usable[] = $normalized;
        }

        $venues = Venue::query()->get();
        $newVenues = collect($usable)
            ->where('type', MatchType::Home->value)
            ->filter(fn (array $row): bool => !$this->venues->match($venues, $row['venue'], $row['city'], $row['venue_code']))
            ->map(fn (array $row): string => $row['venue'])->unique()->values()->all();
        $nameVariants = collect($usable)->map(function (array $row) use ($venues): ?array {
            [$venue, $method] = $this->venues->matchDetail($venues, $row['venue'], $row['city'], $row['venue_code']);

            return $venue && in_array($method, [ImportVenues::METHOD_ALIAS, ImportVenues::METHOD_NORMALIZED], true)
                ? ['source' => $row['venue'], 'venue' => $venue->name, 'method' => $method]
                : null;
        })->filter()->unique(fn (array $match): string => $match['source'].'|'.$match['venue'])->values()->all();
        $teams = Team::query()->get();
        $matcher = ClubTeamMatcher::forCurrentClub();
        $unknownTeams = collect($usable)->flatMap(fn (array $row): array => [$row['home_team'], $row['away_team']])
            ->filter(fn (string $name): bool => $matcher->isOwnTeam($name) && $this->matchTeam($name, $teams) === null)
            ->unique()->values()->all();

        return [
            'rows' => $usable, 'usable' => count($usable), 'skipped' => $skipped, 'past' => $past,
            'new_venues' => $newVenues, 'unknown_teams' => $unknownTeams, 'name_variants' => $nameVariants,
            'categories' => $existing ? $this->categorize($usable, $existing) : [],
        ];
    }

    public function importNewVersion(string $path, string $name, ?int $userId = null): ScheduleVersion
    {
        $report = $this->dryRun($path);
        $previous = ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->first();

        return DB::transaction(function () use ($report, $name, $userId, $previous): ScheduleVersion {
            $version = ScheduleVersion::create(['name' => $name, 'status' => ScheduleVersionStatus::Draft, 'created_by' => $userId]);
            $venues = Venue::query()->get();
            $teams = Team::query()->get();
            $courts = collect();
            // The previous schedule is the reference point per match code;
            // keyed once instead of a query per row.
            $previousMatches = $previous
                ? $previous->matches()->with(['venue', 'court'])->get()->keyBy('match_code')
                : collect();
            foreach ($report['rows'] as $row) {
                $previousMatch = $previousMatches->get($row['code']);
                $this->createMatch($version, $row, $venues, $teams, $courts, $previousMatch, $userId);
            }
            $this->applyTeamDefaults($version);

            return $version->refresh();
        });
    }

    /**
     * Compares the file with what is stored in the version.
     *
     * @return array{equal: bool, checked: int, differences: list<array{code: string, field: string, source: mixed, database: mixed}>}
     */
    public function verify(string $path, ScheduleVersion $version): array
    {
        $report = $this->dryRun($path);
        $stored = $version->matches()->with(['venue', 'court'])->get()->keyBy('match_code');
        $venues = Venue::query()->get();
        $differences = [];
        foreach ($report['rows'] as $row) {
            $match = $stored->pull($row['code']);
            if (!$match) {
                $differences[] = ['code' => $row['code'], 'field' => 'match', 'source' => 'present', 'database' => 'missing'];

                continue;
            }
            $sourceVenue = $this->venues->match($venues, $row['venue'], $row['city'], $row['venue_code']);
            $fields = [
                'date' => [$row['date'], $match->date?->toDateString()],
                'time' => [$row['time'], CellValue::time($match->time)],
            ];
            $isHome = $row['type'] === MatchType::Home->value;
            if ($isHome) {
                $fields['court'] = [$row['court'], $match->court?->name];
            }
            foreach ($fields as $field => [$source, $saved]) {
                if ((string) $source !== (string) $saved) {
                    $differences[] = ['code' => $row['code'], 'field' => $field, 'source' => $source, 'database' => $saved];
                }
            }
            if ($isHome && $sourceVenue?->id !== $match->venue_id) {
                $differences[] = [
                    'code' => $row['code'], 'field' => 'venue',
                    'source' => $row['venue'], 'database' => $match->venue?->name,
                ];
            }
        }
        foreach ($stored as $match) {
            // Played matches are not in the file and skipped by the import;
            // the check should not miss them either.
            if ($this->rows->isPlayed($match->date?->toDateString())) {
                continue;
            }
            $differences[] = ['code' => $match->match_code, 'field' => 'match', 'source' => 'missing', 'database' => 'present'];
        }

        return ['equal' => $differences === [], 'checked' => count($report['rows']), 'differences' => $differences];
    }

    /**
     * Synchronises an existing draft. Choices hold per match id one of the
     * CHOICE_* values for conflicts and removed rows.
     *
     * @param  array<int, string>  $choices
     * @return array<string, int> count per category
     */
    public function updateVersion(string $path, ScheduleVersion $version, array $choices = []): array
    {
        if (!$version->isDraft()) {
            throw new InvalidArgumentException(__('Alleen een conceptversie kan worden bijgewerkt.'));
        }
        $report = $this->dryRun($path, $version);
        $counts = array_map('count', $report['categories']);

        DB::transaction(function () use ($report, $version, $choices): void {
            // Venues, teams and the matches to change are the same for the
            // whole run; reading them inside the loop read the venue table
            // three hundred times for a file of three hundred matches.
            $venues = Venue::query()->get();
            $teams = Team::query()->get();
            $courts = collect();
            $existing = $this->matchesToChange($report);

            foreach ([self::CATEGORY_AUTOSYNC, self::CATEGORY_SOURCE_UPDATE, self::CATEGORY_CONFLICT] as $category) {
                foreach ($report['categories'][$category] as $item) {
                    $match = $existing[$item['match_id']] ?? FixtureMatch::findOrFail($item['match_id']);
                    $row = $item['row'];
                    $choice = $choices[$match->id] ?? ($category === self::CATEGORY_CONFLICT ? self::CHOICE_MANUAL : self::CHOICE_SOURCE);
                    $updates = [
                        'original_date' => $row['date'], 'original_time' => $row['time'],
                        'original_venue' => $row['venue'], 'original_court' => $row['court'],
                    ];
                    if ($category === self::CATEGORY_SOURCE_UPDATE || $choice === self::CHOICE_SOURCE) {
                        $venue = $this->venues->forRow($row, $venues);
                        if ($venue) {
                            $venues->push($venue);
                        }
                        $court = $this->venues->courtFor($courts, $venue, $row['court']);
                        $updates += ['date' => $row['date'], 'time' => $row['time'], 'venue_id' => $venue?->id, 'court_id' => $court?->id];
                    }
                    $old = $match->only(array_keys($updates));
                    $match->update($updates);
                    activity('planner-import')->performedOn($match)
                        ->withProperties(['old' => $old, 'new' => $updates, 'category' => $category])
                        ->log('Wedstrijd bijgewerkt uit import');
                }
            }

            foreach ($report['categories'][self::CATEGORY_REMOVED] as $item) {
                if (($choices[$item['match_id']] ?? self::CHOICE_KEEP) === self::CHOICE_DELETE) {
                    FixtureMatch::find($item['match_id'])?->delete();
                }
            }

            foreach ($report['categories'][self::CATEGORY_NEW] as $row) {
                $this->createMatch($version, $row, $venues, $teams, $courts);
            }
            $this->applyTeamDefaults($version);
        });

        return $counts;
    }

    /** Fills time, venue, duration, scorer, referee and courts from the home team's defaults. */
    public function applyTeamDefaults(ScheduleVersion $version): void
    {
        $this->teamDefaults->apply($version);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<mixed>>
     */
    private function categorize(array $rows, ScheduleVersion $version): array
    {
        $existing = $version->matches->keyBy('match_code');
        $categories = array_fill_keys([
            self::CATEGORY_NEW, self::CATEGORY_UNCHANGED, self::CATEGORY_AUTOSYNC,
            self::CATEGORY_SOURCE_UPDATE, self::CATEGORY_CONFLICT, self::CATEGORY_REMOVED,
        ], []);
        foreach ($rows as $row) {
            $match = $existing->pull($row['code']);
            if (!$match) {
                $categories[self::CATEGORY_NEW][] = $row;

                continue;
            }
            $sourceEqual = $match->original_date?->toDateString() === $row['date'] && substr((string) $match->original_time, 0, 5) === $row['time'];
            $planningEqual = $match->date?->toDateString() === $row['date'] && substr((string) $match->time, 0, 5) === $row['time'];
            $category = match (true) {
                $sourceEqual && $planningEqual => self::CATEGORY_UNCHANGED,
                $planningEqual => self::CATEGORY_AUTOSYNC,
                $match->date?->toDateString() === $match->original_date?->toDateString() => self::CATEGORY_SOURCE_UPDATE,
                default => self::CATEGORY_CONFLICT,
            };
            $categories[$category][] = ['row' => $row, 'match_id' => $match->id];
        }
        foreach ($existing as $match) {
            // A played match is no longer in the federation file. Offering it
            // as "removed" filled the choice list with matches where nothing
            // is left to choose.
            if ($this->rows->isPlayed($match->date?->toDateString())) {
                continue;
            }
            $categories[self::CATEGORY_REMOVED][] = ['match_id' => $match->id];
        }

        return $categories;
    }

    /**
     * Creates a match for a row. With a previous match (same code in the
     * published version) the original_* reference point comes from there.
     *
     * @param  array<string, mixed>  $row
     * @param  Collection<int, Venue>  $venues
     * @param  Collection<int, Team>  $teams
     * @param  Collection<string, mixed>  $courts
     */
    private function createMatch(
        ScheduleVersion $version,
        array $row,
        Collection $venues,
        Collection $teams,
        Collection $courts,
        ?FixtureMatch $previous = null,
        ?int $userId = null,
    ): FixtureMatch {
        $venue = $this->venues->forRow($row, $venues);
        if ($venue) {
            $venues->push($venue);
        }
        $sitting = $this->sittingVolleyball->classify($row['home_team'], $row['away_team'], $venue, MatchType::from($row['type']));
        $type = $sitting['match_type'];
        $court = $this->venues->courtFor($courts, $venue, $row['court']);
        $homeTeam = $this->matchTeam($row['home_team'], $teams);
        $awayTeam = $this->matchTeam($row['away_team'], $teams);
        $usePrevious = $type === MatchType::Home && $previous;

        return FixtureMatch::create([
            'version_id' => $version->id,
            'date' => $row['date'], 'time' => $row['time'],
            'duration_minutes' => $homeTeam?->default_match_duration ?: 120,
            'home_team_name' => $row['home_team'], 'away_team_name' => $row['away_team'],
            'home_team_id' => $homeTeam?->id, 'away_team_id' => $awayTeam?->id,
            'pool' => $row['pool'], 'match_code' => $row['code'], 'region' => $row['region'],
            'match_type' => $type, 'venue_id' => $venue?->id, 'court_id' => $court?->id,
            'is_sitting_volleyball' => $sitting['is_sitting_volleyball'],
            'original_date' => $usePrevious ? $previous->date : $row['date'],
            'original_time' => $usePrevious ? $previous->time : $row['time'],
            'original_venue' => $usePrevious ? $previous->venue?->name : $row['venue'],
            'original_court' => $usePrevious ? $previous->court?->name : $row['court'],
            'created_by' => $userId,
        ]);
    }

    /**
     * The matches to change, loaded at once and keyed by id.
     *
     * @param  array<string, mixed>  $report
     * @return Collection<int, FixtureMatch>
     */
    private function matchesToChange(array $report): Collection
    {
        $ids = collect([self::CATEGORY_AUTOSYNC, self::CATEGORY_SOURCE_UPDATE, self::CATEGORY_CONFLICT])
            ->flatMap(fn (string $category): array => array_column($report['categories'][$category] ?? [], 'match_id'))
            ->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : FixtureMatch::query()->whereKey($ids)->get()->keyBy('id');
    }

    /** @param  Collection<int, Team>  $teams */
    private function matchTeam(string $name, Collection $teams): ?Team
    {
        $codes = $this->conflicts->detectTeams($name, $teams);

        return $teams->first(fn (Team $team): bool => in_array($team->code, $codes, true));
    }
}
