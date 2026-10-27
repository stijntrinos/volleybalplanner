<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\DutyOverviewImage;
use App\Planner\Services\DutyTallyService;
use App\Planner\Support\DateFormat;
use App\Support\Tenancy\CurrentClub;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Duty distribution per team (module duties), measured against the minimum.
 * The minimum columns only show with volunteer_points, the VolleyStars column
 * only with volleystars.
 */
class DutiesDashboard extends Page
{
    use HasPlannerPage,
        HasVersionSelection;

    /** Columns that are summed in the totals row, when present. */
    private const TOTAL_COLUMNS = ['minimum', 'earned', 'referee', 'scorer', 'hall_duty', 'volleystars', 'planned', 'total', 'remaining'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 8;

    protected string $view = 'planner::filament.pages.duties-dashboard';

    protected Width|string|null $maxContentWidth = 'full';

    public ?int $versionId = null;

    /** @var list<array<string, mixed>> */
    public array $tallies = [];

    public string $search = '';

    public string $sort = 'team_asc';

    /** Optional period as Y-m-d; empty means the whole version. */
    public string $fromDate = '';

    public string $toDate = '';

    /** A second schedule version to compare the shortfall per team with. */
    public ?int $compareVersionId = null;

    /** @var array<string, int> team code => shortfall to the minimum in the compared version */
    public array $comparison = [];

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::Duties];
    }

    public static function getNavigationLabel(): string
    {
        return __('Taken-dashboard');
    }

    public function getTitle(): string
    {
        return __('Taken-dashboard');
    }

    public function mount(): void
    {
        $this->versionId = $this->viewableVersionId();
        $this->load();
    }

    public function updatedVersionId(): void
    {
        $this->load();
    }

    public function updatedFromDate(): void
    {
        $this->load();
    }

    public function updatedToDate(): void
    {
        $this->load();
    }

    public function updatedCompareVersionId(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->validate([
            'fromDate' => ['nullable', 'date_format:Y-m-d'],
            'toDate' => ['nullable', 'date_format:Y-m-d', ...($this->fromDate !== '' ? ['after_or_equal:fromDate'] : [])],
        ], [
            'fromDate.date_format' => __('Kies een geldige begindatum.'),
            'toDate.date_format' => __('Kies een geldige einddatum.'),
            'toDate.after_or_equal' => __('De einddatum moet op of na de begindatum liggen.'),
        ]);

        $this->tallies = $this->versionId
            ? app(DutyTallyService::class)->perTeam(ScheduleVersion::findOrFail($this->versionId), $this->fromDate ?: null, $this->toDate ?: null)
            : [];
        // The shortfall per version is the shortfall to the minimum, only for
        // teams with a minimum.
        $this->comparison = $this->compareVersionId && $this->compareVersionId !== $this->versionId && $this->withVolunteerPoints()
            ? collect(app(DutyTallyService::class)->perTeam(ScheduleVersion::findOrFail($this->compareVersionId), $this->fromDate ?: null, $this->toDate ?: null))
                ->filter(fn (array $row): bool => $row['has_minimum'])
                ->mapWithKeys(fn (array $row): array => [$row['team'] => $row['remaining']])
                ->all()
            : [];
    }

    public function withVolunteerPoints(): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::VolunteerPoints);
    }

    public function withVolleystars(): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::Volleystars);
    }

    /** The period in words, for the heading of the image. */
    public function getPeriodLabelProperty(): string
    {
        $from = DateFormat::date($this->fromDate ?: null);
        $to = DateFormat::date($this->toDate ?: null);

        return match (true) {
            $from && $to => __(':from t/m :to', ['from' => $from, 'to' => $to]),
            (bool) $from => __('Vanaf :date', ['date' => $from]),
            (bool) $to => __('Tot en met :date', ['date' => $to]),
            default => __('Hele seizoen'),
        };
    }

    /**
     * The overview as shown on screen, with search and sort, as a PNG to share.
     */
    public function exportImage(): ?StreamedResponse
    {
        try {
            $content = app(DutyOverviewImage::class)->png($this->imageRows(), $this->getTotalsProperty(), $this->getPeriodLabelProperty());
        } catch (RuntimeException $exception) {
            report($exception);
            Notification::make()->title(__('Afbeelding maken mislukt'))->body(__('Probeer het later opnieuw.'))->danger()->send();

            return null;
        }

        $name = 'duty-overview'.($this->toDate ? '-until-'.$this->toDate : '').'.png';

        return response()->streamDownload(fn () => print $content, $name, ['Content-Type' => 'image/png']);
    }

    /**
     * The filtered rows plus the status label DutyOverviewImage prints.
     *
     * @return list<array<string, mixed>>
     */
    public function imageRows(): array
    {
        return array_map(
            fn (array $row): array => $row + ['status_label' => $this->statusLabel($row)],
            $this->getFilteredTalliesProperty(),
        );
    }

    /** @return array<string, int> */
    public function getTotalsProperty(): array
    {
        $totals = array_fill_keys(self::TOTAL_COLUMNS, 0);

        foreach ($this->tallies as $row) {
            foreach (self::TOTAL_COLUMNS as $column) {
                $totals[$column] += (int) ($row[$column] ?? 0);
            }
        }

        return $totals;
    }

    /** @return list<array<string, mixed>> */
    public function getFilteredTalliesProperty(): array
    {
        $tallies = collect($this->tallies)
            ->when($this->search !== '', fn ($rows) => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['team']), mb_strtolower($this->search)),
            ));

        return match ($this->sort) {
            'team_asc' => $tallies->sortBy('team', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'referee_desc' => $tallies->sortBy([['referee', 'desc'], ['total', 'desc']])->values()->all(),
            'scorer_desc' => $tallies->sortBy([['scorer', 'desc'], ['total', 'desc']])->values()->all(),
            'hall_duty_desc' => $tallies->sortBy([['hall_duty', 'desc'], ['total', 'desc']])->values()->all(),
            'volleystars_desc' => $tallies->sortBy([['volleystars', 'desc'], ['total', 'desc']])->values()->all(),
            'remaining_desc' => $tallies->sortBy([['remaining', 'desc'], ['team', 'asc']])->values()->all(),
            'percentage_asc' => $tallies->sortBy(fn (array $row): float => $row['percentage'] ?? PHP_INT_MAX)->values()->all(),
            default => $tallies->sortByDesc('planned')->values()->all(),
        };
    }

    /**
     * The summary is about the points from the duty planning: what the
     * planning itself has distributed.
     *
     * @return array{teams: int, average: float, minimum: int, maximum: int, busiest_team: ?string, below_minimum: int}
     */
    public function getSummaryProperty(): array
    {
        $tallies = collect($this->tallies);
        $busiest = $tallies->sortByDesc('planned')->first();

        return [
            'teams' => $tallies->count(),
            'average' => round((float) $tallies->avg('planned'), 1),
            'minimum' => (int) ($tallies->min('planned') ?? 0),
            'maximum' => (int) ($tallies->max('planned') ?? 0),
            'busiest_team' => $busiest['team'] ?? null,
            'below_minimum' => $tallies->filter(fn (array $row): bool => $this->isBelowMinimum($row))->count(),
        ];
    }

    /** The name of the compared version for the column heading. */
    public function getCompareNameProperty(): ?string
    {
        return $this->compareVersionId && $this->compareVersionId !== $this->versionId && $this->withVolunteerPoints()
            ? ScheduleVersion::query()->whereKey($this->compareVersionId)->value('name')
            : null;
    }

    /**
     * What the column "still to fill" shows: the shortfall to the minimum, as
     * a negative number what was earned above it, and nothing without one.
     */
    public function remainingLabel(array $row): string
    {
        return match (true) {
            !$row['has_minimum'] => '',
            $row['above_minimum'] > 0 => '-'.$row['above_minimum'],
            default => (string) $row['remaining'],
        };
    }

    /**
     * The difference in shortfall between the compared and the shown
     * version: positive means the team has less shortfall in the shown one.
     */
    public function differenceLabel(array $row): string
    {
        if (!$row['has_minimum'] || !array_key_exists($row['team'], $this->comparison)) {
            return '';
        }

        $difference = $this->comparison[$row['team']] - $row['remaining'];

        return $difference === 0 ? '0' : ($difference > 0 ? '+' : '').$difference;
    }

    /** "72,0%", "120,0% boven doel" or "Geen minimum"; empty without volunteer points. */
    public function statusLabel(array $row): string
    {
        if (!$this->withVolunteerPoints()) {
            return '';
        }
        if (!$row['has_minimum']) {
            return __('Geen minimum');
        }

        $text = PointsBalance::percentageText($row['percentage']);

        return $row['above_minimum'] > 0 ? __(':percentage boven doel', ['percentage' => $text]) : $text;
    }

    public function isAboveTarget(array $row): bool
    {
        return $row['has_minimum'] && $row['above_minimum'] > 0;
    }

    public function isBelowMinimum(array $row): bool
    {
        return $row['has_minimum'] && $row['remaining'] > 0;
    }
}
