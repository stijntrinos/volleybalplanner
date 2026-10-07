<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeField;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\PrimaryColor;
use App\Support\Tenancy\CurrentClub;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Excel and PDF exports of the planning, the venue occupancy and the Nevobo
 * changes, plus the day schedule as image/PDF.
 */
class ExportService
{
    public function __construct(
        private readonly SpreadsheetWriter $writer,
        private readonly ConflictEngine $conflicts,
        private readonly LockerRoomPlanner $lockerRooms,
        private readonly DayScheduleImage $daySchedule,
    ) {}

    public function planningExcel(ScheduleVersion $version): string
    {
        $header = $this->planningHeader();
        $sheets = $version->matches()->whereNotNull('date')->with(['venue', 'court'])->orderBy('date')->orderBy('time')->get()
            ->groupBy(fn (FixtureMatch $match): string => $match->venue?->name ?: __('Uitwedstrijden'))
            ->map(fn (Collection $matches): array => [$header, ...$matches->map(fn (FixtureMatch $match): array => $this->row($match))->all()])
            ->all();

        return $this->writer->write($sheets ?: [__('Planning') => [$header]]);
    }

    public function dayExcel(ScheduleVersion $version, string $date, ?int $venueId = null): string
    {
        $rows = $version->matches()->whereDate('date', $date)
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->with(['venue', 'court'])->orderBy('time')->get()->map(fn (FixtureMatch $match): array => $this->row($match))->all();

        return $this->writer->write([__('Dagplanning') => [$this->planningHeader(), ...$rows]]);
    }

    public function conflictsExcel(ScheduleVersion $version): string
    {
        // The message alone did not say what it was about; the match next to
        // it turns the report into a work list.
        $header = [__('Niveau'), __('Type'), __('Melding'), __('Wedstrijd'), __('Gerelateerde wedstrijd')];
        $rows = collect($this->conflicts->scanVersion($version))
            ->map(fn (array $finding): array => [
                $finding['level'], $finding['type'], $finding['message'],
                $finding['subject_match_id'] ?? '', $finding['match_id'] ?? '',
            ])
            ->all();

        return $this->writer->write([__('Conflicten') => [$header, ...$rows]]);
    }

    /** All home match days in the period on one sheet, with the duties per match (duties module). */
    public function dutiesExcel(ScheduleVersion $version, string $fromDate, string $toDate): string
    {
        $matches = $version->matches()
            ->whereBetween('date', [$fromDate, $toDate])
            ->where('match_type', MatchType::Home)
            ->whereNotNull('time')
            ->with(['venue', 'court'])
            ->orderBy('date')->orderBy('time')->get();
        $hallDutiesByDate = $version->hallDuties()
            ->whereBetween('date', [$fromDate, $toDate])
            ->with(['team', 'venue'])->orderBy('date')->orderBy('start_time')->get()
            ->groupBy(fn (HallDuty $duty): string => $duty->date->toDateString());
        $header = [
            __('Datum'), __('Tijd'), __('Eindtijd'), __('Sporthal'), __('Veld'), __('Thuisteam'), __('Uitteam'),
            __('Wedstrijdcode'), __('Fluiten'), __('Tellen'), __('Zaalwacht'), __('Opmerking'),
        ];

        $rows = $matches->map(function (FixtureMatch $match) use ($hallDutiesByDate): array {
            $start = substr((string) $match->time, 0, 5);
            $end = CarbonImmutable::createFromFormat('H:i', $start)->addMinutes((int) $match->duration_minutes)->format('H:i');
            $duties = $hallDutiesByDate->get($match->date->toDateString(), collect())
                ->filter(fn (HallDuty $duty): bool => $duty->venue_id === $match->venue_id
                    && substr((string) $duty->start_time, 0, 5) <= $start
                    && substr((string) $duty->end_time, 0, 5) > $start);

            return [
                $match->date->toDateString(), $start, $end, $match->venue?->name,
                $match->court?->name, $match->home_team_name, $match->away_team_name,
                $match->match_code, $match->referee ?: '—', $match->scorer ?: '—',
                $duties->map(fn (HallDuty $duty): ?string => $duty->team?->code
                    ? $duty->team->code.' ('.substr((string) $duty->start_time, 0, 5).'–'.substr((string) $duty->end_time, 0, 5).')'
                    : null)->filter()->unique()->implode(', ') ?: '—',
                $match->notes ?: '',
            ];
        })->all();

        return $this->writer->write([__('FTZ wedstrijddagen') => [$header, ...$rows]]);
    }

    /**
     * The occupancy overview as a sheet per venue, with the same columns as
     * on screen. With match details each match day gets a line per match
     * below the day line.
     *
     * @param  array<string, mixed>  $overview  VenueOccupancyService::overview()
     */
    public function venueOccupancyExcel(array $overview, bool $withDetails = false): string
    {
        $header = [
            __('Datum'), __('Dag'), __('Beschikbaar vanaf'), __('Beschikbaar tot'), __('Wedstrijden'),
            __('Eerste'), __('Laatste'), __('Einde laatste'), __('Status'), __('Notitie'),
        ];
        $sheets = [];

        foreach ($overview['venues'] as $venue) {
            $rows = [$header];

            foreach ($venue['rows'] as $row) {
                $rows[] = [
                    $row['date'], $row['day'],
                    $row['available_from'] ?? '—', $row['available_until'] ?? '—',
                    $row['matches'],
                    $row['first'] ?? '—', $row['last'] ?? '—', $row['end_of_last'] ?? '—',
                    VenueOccupancyService::statusLabel($row['status']),
                    $row['note'] ?? '',
                ];

                if ($withDetails) {
                    foreach ($row['details'] as $detail) {
                        $rows[] = [
                            '', $detail['code'], $detail['home'], $detail['away'],
                            $detail['court'] ?? '', $detail['start'] ?? '', $detail['end'] ?? '',
                            $detail['duration'], '', '',
                        ];
                    }
                }
            }

            $sheets[mb_substr($venue['venue'], 0, 31)] = $rows;
        }

        return $this->writer->write($sheets ?: [__('Bezetting') => [$header]]);
    }

    /** @param array<string, mixed> $overview */
    public function venueOccupancyPdf(array $overview, bool $withDetails = false): string
    {
        return Pdf::loadView('planner::exports.venue-occupancy', [
            'overview' => $overview,
            'withDetails' => $withDetails,
            'primaryColor' => $this->settingsColor(),
        ])->setPaper('a4', 'landscape')->output();
    }

    /** @param list<array<string, mixed>> $changes  NevoboChangeService::forVersion() */
    public function nevoboChangesExcel(array $changes): string
    {
        $header = [__('Wedstrijdcode'), __('Datum'), __('Team thuis'), __('Team uit'), __('Veld'), __('Oud'), __('Nieuw'), __('Status')];
        $rows = collect($changes)->flatMap(fn (array $match) => collect($match['fields'])->map(fn (array $field): array => [
            $match['code'], $match['date'], $match['home_team'], $match['away_team'],
            self::fieldLabel($field['field']), $field['old'], $field['new'], $field['processed'] ? __('Verwerkt') : __('Openstaand'),
        ]))->values()->all();

        return $this->writer->write([__('Nevobo wijzigingen') => [$header, ...$rows]]);
    }

    /** @param list<array<string, mixed>> $changes */
    public function nevoboChangesPdf(array $changes): string
    {
        return Pdf::loadView('planner::exports.nevobo-changes', [
            'changes' => $changes,
            'primaryColor' => $this->settingsColor(),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function planningPdf(ScheduleVersion $version): string
    {
        $matches = $version->matches()->whereNotNull('date')->with(['venue', 'court'])->orderBy('date')->orderBy('time')->get();

        return Pdf::loadView('planner::exports.planning', [
            'version' => $version,
            'matches' => $matches,
            'showDuties' => $this->hasFeature(PlannerFeature::Duties),
            'primaryColor' => $this->settingsColor(),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function daySchedulePdf(ScheduleVersion $version, string $date, ?int $venueId = null, ?string $footerText = null): string
    {
        $image = $this->dayScheduleJpg($version, $date, $venueId, $footerText);
        // The paper size follows the actual image; it is 16:9, but reading it
        // is cheaper than fixing that size here once more.
        [$width, $height] = getimagesizefromstring($image) ?: [1920, 1080];
        $scale = 1200 / max(1, $width);

        return Pdf::loadHTML('<!doctype html><html><head><style>@page{margin:0}body{margin:0}img{display:block;width:100%;height:100%}</style></head><body><img src="data:image/jpeg;base64,'.base64_encode($image).'"></body></html>')
            ->setPaper([0, 0, 1200, (int) round($height * $scale)])
            ->output();
    }

    public function dayScheduleJpg(ScheduleVersion $version, string $date, ?int $venueId = null, ?string $footerText = null): string
    {
        return $this->daySchedule->jpg(...$this->dayScheduleParts($version, $date, $venueId, $footerText));
    }

    /** The HTML behind the image; available separately so the layout can be tested without Chrome. */
    public function dayScheduleHtml(ScheduleVersion $version, string $date, ?int $venueId = null, ?string $footerText = null): string
    {
        return $this->daySchedule->html(...$this->dayScheduleParts($version, $date, $venueId, $footerText));
    }

    /** @return array{0: array<string, mixed>, 1: Collection<int, Court>} */
    private function dayScheduleParts(ScheduleVersion $version, string $date, ?int $venueId, ?string $footerText): array
    {
        $data = $this->dayScheduleData($version, $date, $venueId) + ['footer_text' => $footerText];

        return [$data, $this->dayScheduleCourts($data['matches'])];
    }

    /** @return Collection<int, Court> */
    private function dayScheduleCourts(Collection $matches): Collection
    {
        $courtIds = $matches->flatMap(fn (FixtureMatch $match): array => [$match->court_id, $match->second_court_id])->filter()->unique();

        return Court::query()->whereIn('id', $courtIds)->with('venue')->orderBy('sort_order')->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    private function dayScheduleData(ScheduleVersion $version, string $date, ?int $venueId = null): array
    {
        $matches = $version->matches()->where('match_type', MatchType::Home)->whereDate('date', $date)
            ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
            ->whereNotNull('time')->with(['venue', 'court', 'homeTeam'])->orderBy('time')->get();
        $showDuties = $this->hasFeature(PlannerFeature::Duties);
        $showLockerRooms = $this->hasFeature(PlannerFeature::LockerRooms);

        $lockerRooms = ['assignments' => [], 'warnings' => []];
        if ($showLockerRooms) {
            $lockerRooms = $this->lockerRooms->plan($this->lockerRoomInput($matches));
        }

        $hallDuties = $showDuties
            ? $version->hallDuties()->whereDate('date', $date)
                ->when($venueId, fn ($query) => $query->where('venue_id', $venueId))
                ->with(['venue', 'team'])->orderBy('start_time')->get()
            : collect();

        return [
            'version' => $version,
            'date' => $date,
            'matches' => $matches,
            'hall_duties' => $hallDuties,
            'locker_rooms' => $lockerRooms,
            'settings' => PlannerSetting::current(),
            'show_duties' => $showDuties,
            'show_locker_rooms' => $showLockerRooms,
        ];
    }

    /**
     * Only venues where the club assigns locker rooms; otherwise matches
     * elsewhere would occupy rooms that venue does not have.
     *
     * @param  Collection<int, FixtureMatch>  $matches
     * @return Collection<int, array<string, mixed>>
     */
    private function lockerRoomInput(Collection $matches): Collection
    {
        $teams = Team::query()->get();
        $matcher = ClubTeamMatcher::forCurrentClub();

        return $matches->filter(fn (FixtureMatch $match): bool => (bool) $match->venue?->show_locker_rooms_on_schedule)
            ->map(function (FixtureMatch $match) use ($teams, $matcher): array {
                $homeTeam = $teams->first(fn (Team $team): bool => $matcher->isOwnTeamWithCode($match->home_team_name, $team->code))
                    ?? $match->homeTeam;
                $start = substr((string) $match->time, 0, 5);

                return [
                    'id' => $match->id, 'start' => $start,
                    'end' => CarbonImmutable::createFromFormat('H:i', $start)->addMinutes((int) $match->duration_minutes)->format('H:i'),
                    'home' => (string) $match->home_team_name, 'away' => (string) $match->away_team_name,
                    'home_category' => $homeTeam?->locker_room_category,
                ];
            })->values();
    }

    /** @return list<string> */
    private function planningHeader(): array
    {
        $duties = $this->hasFeature(PlannerFeature::Duties) ? [__('Scheidsrechter'), __('Teller')] : [];

        return [__('Datum'), __('Tijd'), __('Sporthal'), __('Veld'), __('Thuis'), __('Uit'), __('Poule'), __('Code'), ...$duties, __('Status')];
    }

    /** @return list<mixed> */
    private function row(FixtureMatch $match): array
    {
        $changed = $match->original_date?->toDateString() !== $match->date?->toDateString()
            || ($match->original_time && substr($match->original_time, 0, 5) !== substr((string) $match->time, 0, 5));
        $duties = $this->hasFeature(PlannerFeature::Duties) ? [$match->referee, $match->scorer] : [];

        return [
            $match->date?->format('d-m-Y'), substr($match->time ?? '', 0, 5), $match->venue?->name, $match->court?->name,
            $match->home_team_name, $match->away_team_name, $match->pool, $match->match_code,
            ...$duties, $changed ? __('Gewijzigd') : __('Gepland'),
        ];
    }

    private static function fieldLabel(mixed $field): string
    {
        return NevoboChangeField::tryFrom((string) $field)?->getLabel() ?? ucfirst((string) $field);
    }

    private function hasFeature(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }

    private function settingsColor(): string
    {
        return PrimaryColor::from(PlannerSetting::current()->primary_color);
    }
}
