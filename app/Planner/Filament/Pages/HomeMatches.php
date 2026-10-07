<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Enums\MatchType;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\AuthorizesMatches;
use App\Planner\Filament\Pages\Concerns\ExportsDayOverview;
use App\Planner\Filament\Pages\Concerns\ManagesMatchModal;
use App\Planner\Filament\Pages\Concerns\ManagesPlanningAssistant;
use App\Planner\Filament\Pages\Concerns\ManagesVolleystarsModal;
use App\Planner\Filament\Pages\Concerns\MovesMatches;
use App\Planner\Filament\Pages\Concerns\SearchesMatches;
use App\Planner\Filament\Pages\Concerns\ShowsErrors;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\UserPreference;
use App\Planner\Services\PlannerService;
use App\Planner\Support\Timetable;
use App\Support\Tenancy\CurrentClub;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

/**
 * The day planner: one match day of one schedule version as a timetable per
 * venue and court, with drag and drop, the planning assistant and the
 * match / Volleystars modals.
 *
 * @property-read array<string, string> $volleystarsRoles
 * @property-read Collection<int, string> $modalCourts
 * @property-read Collection<int, string> $modalVenues
 * @property-read Collection<string, string> $modalOfficials
 */
class HomeMatches extends Page
{
    use AuthorizesMatches,
        ExportsDayOverview,
        HasPlannerPage,
        HasVersionSelection,
        ManagesMatchModal,
        ManagesPlanningAssistant,
        ManagesVolleystarsModal,
        MovesMatches,
        SearchesMatches,
        ShowsErrors;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 1;

    protected string $view = 'planner::filament.pages.home-matches';

    protected Width|string|null $maxContentWidth = 'full';

    /** Resource that manages schedule versions (owned by the resources group). */
    private const VERSION_RESOURCE = 'App\\Planner\\Filament\\Resources\\ScheduleVersions\\ScheduleVersionResource';

    public ?int $versionId = null;

    public string $date;

    public string $dateInput = '';

    public bool $showUnplanned = false;

    /** @var list<int> */
    public array $hiddenVenues = [];

    /** @var array<string, mixed> PlannerService::dayData() */
    public array $day = [];

    public static function getNavigationLabel(): string
    {
        return __('Thuiswedstrijden');
    }

    public function getTitle(): string
    {
        return __('Thuiswedstrijden');
    }

    public function mount(): void
    {
        $preference = UserPreference::query()->where('user_id', auth()->id())->first();

        $this->versionId = ScheduleVersion::query()->whereKey($preference?->active_version_id)->value('id')
            ?? $this->editableVersionId();
        $this->date = $this->validDate(request('datum'))
            ?: $preference?->selected_date?->toDateString()
            ?: $this->firstMatchDate()
            ?: now()->toDateString();
        $this->savePlannerPreference();
        $this->load();
    }

    public function updatedVersionId(): void
    {
        $this->date = $this->firstMatchDate() ?: now()->toDateString();
        $this->savePlannerPreference();
        $this->load();
    }

    /**
     * Both the typed field (dd-MM-yyyy) and the calendar button (yyyy-MM-dd
     * from the browser) end up here; only a date that reads back exactly as
     * entered counts, so garbage stops here.
     */
    public function selectDate(string $date): void
    {
        $chosen = null;
        foreach (['!d-m-Y', '!Y-m-d'] as $format) {
            try {
                $entered = CarbonImmutable::createFromFormat($format, $date);
                if ($entered && $entered->format(ltrim($format, '!')) === $date) {
                    $chosen = $entered->toDateString();
                    break;
                }
            } catch (Throwable) {
                continue;
            }
        }
        if (!$chosen) {
            return;
        }

        $this->date = $chosen;
        $this->savePlannerPreference();
        $this->load();
    }

    public function navigate(int $direction): void
    {
        if (!$this->versionId) {
            return;
        }
        $query = $this->homeMatchDays();
        $date = $direction < 0
            ? $query->whereDate('date', '<', $this->date)->max('date')
            : $query->whereDate('date', '>', $this->date)->min('date');
        if ($date) {
            $this->date = CarbonImmutable::parse($date)->toDateString();
        }
        $this->savePlannerPreference();
        $this->load();
    }

    public function goToToday(): void
    {
        $this->date = now()->toDateString();
        $this->savePlannerPreference();
        $this->load();
    }

    public function load(): void
    {
        $this->dateInput = CarbonImmutable::parse($this->date)->format('d-m-Y');
        $this->day = $this->versionId
            ? app(PlannerService::class)->dayData(ScheduleVersion::query()->findOrFail($this->versionId), $this->date)
            : [];
        $this->collapseVenues();
        $this->dispatch('planner-refreshed');
    }

    /**
     * A new match day starts at the club's own venue. The other venues of that
     * day stay in the bar and can be added with one click; showing them right
     * away made the timetable so wide that you lost the own venue.
     */
    private function collapseVenues(): void
    {
        $open = array_map('intval', $this->day['default_venues'] ?? []);

        $this->hiddenVenues = collect($this->day['venues'] ?? [])
            ->pluck('id')->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => in_array($id, $open, true))
            ->values()->all();
    }

    public function getVersionsUrlProperty(): ?string
    {
        return class_exists(self::VERSION_RESOURCE)
            ? (self::VERSION_RESOURCE)::getUrl('index')
            : null;
    }

    /** @return list<array<string, mixed>> */
    public function getVisibleMatchesProperty(): array
    {
        return collect($this->day['matches'] ?? [])->values()->all();
    }

    /**
     * Per court the column layout of the matches on it. Overlapping matches
     * go side by side instead of on top of each other; a match on two courts
     * counts in both, but is drawn in the first court and uses that layout.
     *
     * @return array<int, array<int, array{column: int, columns: int}>>
     */
    public function getCourtLayoutProperty(): array
    {
        $perCourt = [];
        // A proposal to this day already occupies its court; it goes next to
        // a match instead of over it.
        foreach ([...$this->getVisibleMatchesProperty(), ...($this->day['provisional'] ?? [])] as $match) {
            $start = Timetable::minutes($match['time'] ?: '08:00');
            $block = [
                'id' => (int) $match['id'],
                'start' => $start,
                'end' => $start + (int) ($match['duration_minutes'] ?: 0),
            ];
            foreach ([$match['court_id'] ?? null, $match['second_court_id'] ?? null] as $courtId) {
                if ($courtId) {
                    $perCourt[(int) $courtId][] = $block;
                }
            }
        }

        return array_map(fn (array $blocks): array => Timetable::sideBySide($blocks), $perCourt);
    }

    public function toggleVenue(int $venueId): void
    {
        $this->hiddenVenues = in_array($venueId, $this->hiddenVenues, true)
            ? array_values(array_diff($this->hiddenVenues, [$venueId]))
            : [...$this->hiddenVenues, $venueId];
    }

    /**
     * Both ?datum= in the URL and the date picker come through here; the view
     * parses the value without a safety net, so garbage should stop here.
     */
    private function validDate(mixed $date): ?string
    {
        if (!is_string($date) || $date === '') {
            return null;
        }

        try {
            $chosen = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (Throwable) {
            return null;
        }

        return $chosen && $chosen->format('Y-m-d') === $date ? $chosen->toDateString() : null;
    }

    /**
     * The match day the planner opens on: today when matches are played
     * today, otherwise the next match day. When the season is over, the last
     * one -- the first day of the season took you months back in time, and an
     * empty timetable says nothing.
     */
    private function firstMatchDate(): ?string
    {
        if (!$this->versionId) {
            return null;
        }

        $days = $this->homeMatchDays();
        $today = CarbonImmutable::now()->toDateString();
        $date = $days->clone()->whereDate('date', '>=', $today)->orderBy('date')->value('date')
            ?? $days->clone()->orderByDesc('date')->value('date');

        return $date ? CarbonImmutable::parse($date)->toDateString() : null;
    }

    /** @return Builder<FixtureMatch> */
    private function homeMatchDays()
    {
        return FixtureMatch::query()->where('version_id', $this->versionId)
            ->where('match_type', MatchType::Home)
            ->whereNotNull('date');
    }

    private function savePlannerPreference(): void
    {
        if (!auth()->id() || !app(CurrentClub::class)->get()) {
            return;
        }

        UserPreference::query()->updateOrCreate(
            ['user_id' => auth()->id()],
            ['active_version_id' => $this->versionId, 'selected_date' => $this->date],
        );
    }
}
