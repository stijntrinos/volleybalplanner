<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ExportService;
use App\Planner\Services\VenueOccupancyService;
use App\Planner\Support\DateFormat;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Puts the hall rental next to the schedule per venue and per day, so it shows
 * at a glance where it pinches: a match starting before the venue opens, a day
 * without recorded rental, or a closed venue where a match is played anyway.
 */
class VenueOccupancy extends Page
{
    use HasPlannerPage,
        HasVersionSelection;

    /**
     * Days per step. A whole season over every venue is well over a thousand
     * rows; rendering those at once made the page slow and heavy.
     */
    public const STEP = 31;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 3;

    protected string $view = 'planner::filament.pages.venue-occupancy';

    protected Width|string|null $maxContentWidth = 'full';

    public ?int $versionId = null;

    public string $fromDate = '';

    public string $toDate = '';

    public ?string $venueId = null;

    /** @var array<int, string> */
    public array $statuses = [];

    public bool $matchDaysOnly = false;

    public bool $withDetails = true;

    /** list | calendar */
    public string $display = 'list';

    /** @var array<int|string, int> per venue how many days are listed */
    public array $shownPerVenue = [];

    public int $calendarCount = self::STEP * 3;

    /**
     * The expanded days, as "venueId|date". The matches of a day are only in
     * the HTML once you expand it; sending them up front made a match day a
     * row of fifteen kilobytes.
     *
     * @var list<string>
     */
    public array $openDays = [];

    /**
     * One calculation per request and filter state: the view asks for the
     * overview several times, and the calendar did once more on top.
     *
     * @var array{0: string, 1: array<string, mixed>}|null
     */
    private ?array $overviewCache = null;

    public static function getNavigationLabel(): string
    {
        return __('Sporthalbezetting');
    }

    public function getTitle(): string
    {
        return __('Sporthalbezetting & beschikbaarheid');
    }

    public function mount(): void
    {
        $this->versionId = $this->viewableVersionId();
        $this->setPeriod();
    }

    public function updatedVersionId(): void
    {
        $this->setPeriod();
    }

    /** Another filter gives other days; showing starts at the top again. */
    public function updated(string $property): void
    {
        if (in_array(explode('.', $property)[0], ['versionId', 'fromDate', 'toDate', 'venueId', 'statuses', 'matchDaysOnly'], true)) {
            $this->shownPerVenue = [];
            $this->calendarCount = self::STEP * 3;
        }
    }

    public function showMore(int $venueId): void
    {
        $this->shownPerVenue[$venueId] = $this->shown($venueId) + self::STEP;
    }

    public function toggleDay(int $venueId, string $date): void
    {
        $key = $venueId.'|'.$date;
        $this->openDays = in_array($key, $this->openDays, true)
            ? array_values(array_diff($this->openDays, [$key]))
            : [...$this->openDays, $key];
    }

    public function dayIsOpen(int $venueId, string $date): bool
    {
        return in_array($venueId.'|'.$date, $this->openDays, true);
    }

    public function showMoreCalendar(): void
    {
        $this->calendarCount += self::STEP * 3;
    }

    /** How many days of this venue are listed now. */
    public function shown(int $venueId): int
    {
        return $this->shownPerVenue[$venueId] ?? self::STEP;
    }

    /**
     * The quick filters write into the same fields as the separate status
     * checkboxes, so the screen never shows a filter that differs from what
     * is actually filtered.
     */
    public function chooseFilter(string $name): void
    {
        $this->shownPerVenue = [];
        $this->calendarCount = self::STEP * 3;
        [$this->statuses, $this->matchDaysOnly] = match ($name) {
            'match_days' => [[], true],
            'unavailable' => [[VenueOccupancyService::STATUS_CLOSED, VenueOccupancyService::STATUS_UNKNOWN], false],
            'conflicts' => [[VenueOccupancyService::STATUS_CONFLICT], false],
            default => [[], false],
        };
    }

    /** Which quick filter matches the current selection; 'custom' when adjusted by hand. */
    public function activeFilter(): string
    {
        $chosen = $this->statuses;
        sort($chosen);

        return match (true) {
            $this->matchDaysOnly && $chosen === [] => 'match_days',
            $chosen === [] => 'all',
            $chosen === [VenueOccupancyService::STATUS_CONFLICT] => 'conflicts',
            $chosen === $this->sortedUnavailable() => 'unavailable',
            default => 'custom',
        };
    }

    /** @return array<int|string, string> */
    public function getVenuesProperty(): array
    {
        if (!$this->versionId) {
            return [];
        }

        return Venue::query()->inPlanningOrder()->pluck('name', 'id')->all();
    }

    /** @return array<string, mixed> */
    public function getOverviewProperty(): array
    {
        if (!$this->versionId) {
            return ['venues' => [], 'total' => 0, 'conflicts' => 0, 'from' => now()->toDateString(), 'to' => now()->toDateString()];
        }

        $filters = [
            $this->isoDate($this->fromDate),
            $this->isoDate($this->toDate),
            $this->venueId ? (int) $this->venueId : null,
            array_values(array_intersect($this->statuses, VenueOccupancyService::STATUSES)),
            $this->matchDaysOnly,
        ];
        $key = serialize([$this->versionId, ...$filters]);

        if ($this->overviewCache === null || $this->overviewCache[0] !== $key) {
            $this->overviewCache = [$key, app(VenueOccupancyService::class)->overview(
                ScheduleVersion::findOrFail($this->versionId), ...$filters,
            )];
        }

        return $this->overviewCache[1];
    }

    /**
     * The calendar view groups the same rows per month, so a whole season can
     * be overseen on one screen instead of as a long list.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getCalendarProperty(): array
    {
        $perMonth = [];

        foreach (array_slice($this->calendarRows(), 0, $this->calendarCount) as $row) {
            $perMonth[DateFormat::month($row['date'])][] = $row;
        }

        return $perMonth;
    }

    /** How many days the calendar has in total, including what is not shown yet. */
    public function getCalendarTotalProperty(): int
    {
        return count($this->calendarRows());
    }

    /**
     * Every row of every venue by date; the calendar shows them in steps.
     *
     * @return list<array<string, mixed>>
     */
    private function calendarRows(): array
    {
        $rows = [];
        foreach ($this->getOverviewProperty()['venues'] as $venue) {
            foreach ($venue['rows'] as $row) {
                $rows[] = $row + ['venue' => $venue['venue']];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['date'], $a['venue']] <=> [$b['date'], $b['venue']]);

        return $rows;
    }

    public function excel(): BinaryFileResponse
    {
        $path = app(ExportService::class)->venueOccupancyExcel($this->getOverviewProperty(), $this->withDetails);

        return response()->download($path, 'venue-occupancy.xlsx')->deleteFileAfterSend();
    }

    public function pdf(): StreamedResponse
    {
        $content = app(ExportService::class)->venueOccupancyPdf($this->getOverviewProperty(), $this->withDetails);

        return response()->streamDownload(fn () => print $content, 'venue-occupancy.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function statusLabel(string $status): string
    {
        return VenueOccupancyService::statusLabel($status);
    }

    /** The colour follows the severity: red where something goes wrong, grey where nothing happens. */
    public function statusColor(string $status): string
    {
        return match ($status) {
            VenueOccupancyService::STATUS_AVAILABLE => 'success',
            VenueOccupancyService::STATUS_PARTIAL => 'warning',
            VenueOccupancyService::STATUS_CONFLICT, VenueOccupancyService::STATUS_UNKNOWN => 'danger',
            default => 'gray',
        };
    }

    /** @return array<int, string> */
    private function sortedUnavailable(): array
    {
        $statuses = [VenueOccupancyService::STATUS_CLOSED, VenueOccupancyService::STATUS_UNKNOWN];
        sort($statuses);

        return $statuses;
    }

    /** The schedule covers a whole season; by default we show exactly that period. */
    private function setPeriod(): void
    {
        $version = $this->versionId ? ScheduleVersion::find($this->versionId) : null;
        $first = $version?->matches()->whereNotNull('date')->min('date');
        $last = $version?->matches()->whereNotNull('date')->max('date');

        $this->fromDate = CarbonImmutable::parse($first ?: now())->format(DateFormat::DATE);
        $this->toDate = CarbonImmutable::parse($last ?: now()->addMonths(9))->format(DateFormat::DATE);
    }

    /** A half-typed date must not crash the overview. */
    private function isoDate(string $value): string
    {
        try {
            return CarbonImmutable::createFromFormat(DateFormat::DATE, $value)->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }
}
