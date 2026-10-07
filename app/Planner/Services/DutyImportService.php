<?php

namespace App\Planner\Services;

use App\Planner\Features\PlannerFeature;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Support\CellValue;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\MatchCode;
use App\Planner\Support\TeamCode;
use App\Planner\Support\VenueName;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads the Nevobo duty export: per home match which team referees, scores or
 * does hall duty. The file has one sheet with the (Dutch) columns Seizoen,
 * Datum, Begintijd, Eindtijd, Dienstsoort, Team, Zaalcode, Sporthal, Veld,
 * Wedstrijdcode, Thuisteam and Uitteam.
 *
 * Referee and scorer duties become a fixed assignment on the match, hall
 * duties their own rows. What the planner already filled in stays, unless
 * overwrite is on. Volleystar duties have no match code and belong to the
 * VolleyStars blocks; they are only counted. Requires the duties module.
 */
class DutyImportService
{
    public const REQUIRED = ['Datum', 'Begintijd', 'Eindtijd', 'Dienstsoort', 'Team', 'Wedstrijdcode'];

    private const KIND_REFEREE = 'fluitdienst';

    private const KIND_SCORER = 'teldienst';

    private const KIND_HALL = 'zaaldienst';

    private const KIND_VOLLEYSTARS = 'volleystar-dienst';

    /** @var array<string, string> duty kind => column (and "_auto" flag) on the match */
    private const MATCH_COLUMN = [self::KIND_REFEREE => 'referee', self::KIND_SCORER => 'scorer'];

    /** @var array<string, string> duty kind => key in the "assigned" counts */
    private const TALLY = [self::KIND_REFEREE => 'referee', self::KIND_SCORER => 'scorer', self::KIND_HALL => 'hall_duty'];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * The same decisions as a real import, without writing anything.
     *
     * @return array{rows: int, assigned: array{referee: int, scorer: int, hall_duty: int}, unchanged: int, skipped: list<array{row: int, reason: string}>, unmatched: list<array{row: int, reason: string}>, volleystars: int}
     */
    public function analyze(string $path, ScheduleVersion $version, bool $overwrite = false): array
    {
        return $this->process($path, $version, $overwrite, write: false);
    }

    /** @return array{rows: int, assigned: array{referee: int, scorer: int, hall_duty: int}, unchanged: int, skipped: list<array{row: int, reason: string}>, unmatched: list<array{row: int, reason: string}>, volleystars: int} */
    public function import(string $path, ScheduleVersion $version, bool $overwrite = false): array
    {
        return DB::transaction(fn (): array => $this->process($path, $version, $overwrite, write: true));
    }

    private function process(string $path, ScheduleVersion $version, bool $overwrite, bool $write): array
    {
        if (!app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Duties)) {
            throw new RuntimeException(__('De module diensten staat niet aan voor deze club.'));
        }

        $rows = $this->reader->read($path);
        $missing = array_values(array_diff(self::REQUIRED, array_keys($rows[0] ?? [])));
        if ($missing) {
            throw new InvalidArgumentException(__('Verplichte kolommen ontbreken: :columns', ['columns' => implode(', ', $missing)]));
        }

        $matches = FixtureMatch::query()->where('version_id', $version->id)->get()
            ->keyBy(fn (FixtureMatch $match): string => MatchCode::key($match->match_code));
        $teams = Team::query()->select(Team::PLANNER_COLUMNS)->get();
        $venues = Venue::query()->get();
        $hallDuties = HallDuty::query()->where('version_id', $version->id)->get()
            ->keyBy(fn (HallDuty $duty): string => $this->dutyKey($duty->date->toDateString(), $duty->venue_id, (string) $duty->start_time));

        $report = [
            'rows' => count($rows), 'assigned' => ['referee' => 0, 'scorer' => 0, 'hall_duty' => 0],
            'unchanged' => 0, 'skipped' => [], 'unmatched' => [], 'volleystars' => 0,
        ];

        foreach ($rows as $index => $row) {
            $number = $index + 2; // Row 1 is the header.
            $kind = Str::lower(trim((string) $row['Dienstsoort']));

            if ($kind === self::KIND_VOLLEYSTARS) {
                $report['volleystars']++;

                continue;
            }
            if (!isset(self::TALLY[$kind])) {
                $report['unmatched'][] = ['row' => $number, 'reason' => __('Onbekende dienstsoort ":kind"', ['kind' => $row['Dienstsoort']])];

                continue;
            }

            $team = $this->matchTeam((string) $row['Team'], $teams);
            if (!$team) {
                $report['unmatched'][] = ['row' => $number, 'reason' => __('Team ":team" is onbekend', ['team' => $row['Team']])];

                continue;
            }

            $match = $matches->get(MatchCode::key((string) $row['Wedstrijdcode']));

            $outcome = $kind === self::KIND_HALL
                ? $this->hallDuty($row, $team, $match, $venues, $hallDuties, $version, $overwrite, $write)
                : $this->matchDuty($row, $kind, $team, $match, $overwrite, $write);

            match ($outcome['status']) {
                'assigned' => $report['assigned'][self::TALLY[$kind]]++,
                'unchanged' => $report['unchanged']++,
                'skipped' => $report['skipped'][] = ['row' => $number, 'reason' => $outcome['reason']],
                default => $report['unmatched'][] = ['row' => $number, 'reason' => $outcome['reason']],
            };
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{status: string, reason?: string}
     */
    private function matchDuty(array $row, string $kind, Team $team, ?FixtureMatch $match, bool $overwrite, bool $write): array
    {
        if (!$match) {
            return ['status' => 'unmatched', 'reason' => __('Wedstrijd :code staat niet in deze versie', ['code' => $row['Wedstrijdcode']])];
        }

        $column = self::MATCH_COLUMN[$kind];
        $current = $match->{$column};

        if (TeamCode::equals($current, $team->code)) {
            return ['status' => 'unchanged'];
        }
        if (filled($current) && !$overwrite) {
            return ['status' => 'skipped', 'reason' => __(':code: :kind staat al op ":current", bestand zegt :team', [
                'code' => $row['Wedstrijdcode'], 'kind' => $row['Dienstsoort'], 'current' => $current, 'team' => $team->code,
            ])];
        }

        // Also update during analysis: a second row for the same match in the
        // file then counts as skipped, exactly as when writing.
        $match->{$column} = $team->code;
        $match->{$column.'_auto'} = false;
        if ($write) {
            $match->save();
        }

        return ['status' => 'assigned'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, Venue>  $venues
     * @param  Collection<string, HallDuty>  $existing
     * @return array{status: string, reason?: string}
     */
    private function hallDuty(array $row, Team $team, ?FixtureMatch $match, Collection $venues, Collection $existing, ScheduleVersion $version, bool $overwrite, bool $write): array
    {
        $date = CellValue::date($row['Datum']);
        $start = CellValue::time($row['Begintijd']);
        $end = CellValue::time($row['Eindtijd']);
        if (!$date || !$start || !$end) {
            return ['status' => 'unmatched', 'reason' => __('Datum of tijden van de zaaldienst zijn onleesbaar')];
        }

        $venue = $this->matchVenue((string) ($row['Zaalcode'] ?? ''), (string) ($row['Sporthal'] ?? ''), $venues) ?? $match?->venue;
        if (!$venue) {
            return ['status' => 'unmatched', 'reason' => __('Sporthal ":venue" (:code) is onbekend', [
                'venue' => $row['Sporthal'] ?? '', 'code' => $row['Zaalcode'] ?? '',
            ])];
        }

        $key = $this->dutyKey($date, $venue->id, $start);
        $duty = $existing->get($key);

        if ($duty && $duty->team_id === $team->id) {
            return ['status' => 'unchanged'];
        }
        if ($duty && !$overwrite) {
            return ['status' => 'skipped', 'reason' => __('Zaaldienst :date :start :venue staat al op ":current", bestand zegt :team', [
                'date' => $date, 'start' => $start, 'venue' => $venue->name, 'current' => $duty->team?->code, 'team' => $team->code,
            ])];
        }

        if ($duty) {
            $duty->team_id = $team->id;
            $duty->team_auto = false;
            $duty->end_time = $end;
            $duty->setRelation('team', $team);
        } else {
            $duty = new HallDuty([
                'version_id' => $version->id, 'date' => $date,
                'start_time' => $start, 'end_time' => $end, 'venue_id' => $venue->id,
                'team_id' => $team->id, 'team_auto' => false,
            ]);
            $duty->setRelation('team', $team);
            $existing->put($key, $duty);
        }
        if ($write) {
            $duty->save();
        }

        return ['status' => 'assigned'];
    }

    /**
     * The export names the team with the club name, sometimes with a sponsor
     * in between; the team table only knows the code. First by the code after
     * the club name, and for teams without a category (like "MA+") by the
     * full name.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function matchTeam(string $name, Collection $teams): ?Team
    {
        $matcher = ClubTeamMatcher::forCurrentClub();
        $code = $matcher->ownTeamCode($name);
        if ($code !== null) {
            $key = TeamCode::key($code);
            $team = $teams->first(fn (Team $team): bool => TeamCode::key($team->code) === $key);
            if ($team) {
                return $team;
            }
        }

        return $teams->first(fn (Team $team): bool => TeamCode::equals($team->name, $name))
            ?? $teams->first(fn (Team $team): bool => $matcher->isOwnTeamWithCode($name, $team->code));
    }

    /** @param  Collection<int, Venue>  $venues */
    private function matchVenue(string $venueCode, string $name, Collection $venues): ?Venue
    {
        $code = Str::upper(trim($venueCode));
        if ($code !== '') {
            $venue = $venues->first(fn (Venue $venue): bool => Str::upper((string) $venue->venue_code) === $code);
            if ($venue) {
                return $venue;
            }
        }

        return $name === '' ? null : $venues->first(fn (Venue $venue): bool => VenueName::equals($venue->name, $name));
    }

    private function dutyKey(string $date, ?int $venueId, string $start): string
    {
        return $date.'|'.$venueId.'|'.substr($start, 0, 5);
    }
}
