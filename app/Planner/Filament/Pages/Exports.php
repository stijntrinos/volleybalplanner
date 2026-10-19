<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ExportService;
use App\Planner\Support\DateFormat;
use App\Support\Tenancy\CurrentClub;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * @property-read array<string, string> $matchDays
 * @property-read array<int, string> $venues
 */
class Exports extends Page
{
    use HasPlannerPage,
        HasVersionSelection;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 9;

    protected string $view = 'planner::filament.pages.exports';

    protected Width|string|null $maxContentWidth = 'full';

    public ?int $versionId = null;

    public string $date;

    public ?int $venueId = null;

    public string $dutiesFromDate;

    public string $dutiesToDate;

    /** One-off deviation for this export; the default lives in the planner settings. */
    public string $footerText = '';

    #[Locked]
    public string $defaultFooterText = '';

    public static function getNavigationLabel(): string
    {
        return __('Planning exporteren');
    }

    public function getTitle(): string
    {
        return __('Planning exporteren');
    }

    public function mount(): void
    {
        $this->defaultFooterText = (string) PlannerSetting::current()->schedule_footer;
        $this->footerText = $this->defaultFooterText;
        $this->versionId = $this->viewableVersionId();
        $this->setMatchDate();
        $this->setVenue();
        $this->setDutiesPeriod();
    }

    public function updatedVersionId(): void
    {
        $this->setMatchDate();
        $this->setVenue();
        $this->setDutiesPeriod();
    }

    public function updatedDate(): void
    {
        $this->setVenue();
    }

    public function withDuties(): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::Duties);
    }

    public function resetFooterText(): void
    {
        $this->footerText = $this->defaultFooterText;
        $this->resetErrorBag('footerText');
    }

    public function planningExcel(): BinaryFileResponse
    {
        return $this->downloadExcel(app(ExportService::class)->planningExcel($this->version()), 'match-planning.xlsx');
    }

    public function dayExcel(): BinaryFileResponse
    {
        $this->validateDate();

        return $this->downloadExcel(app(ExportService::class)->dayExcel($this->version(), $this->date, $this->venueId), "day-planning-{$this->date}.xlsx");
    }

    public function conflictsExcel(): BinaryFileResponse
    {
        return $this->downloadExcel(app(ExportService::class)->conflictsExcel($this->version()), 'conflict-report.xlsx');
    }

    public function dutiesExcel(): BinaryFileResponse
    {
        abort_unless($this->withDuties(), 403);
        $this->validate([
            'dutiesFromDate' => ['required', 'date_format:d-m-Y'],
            'dutiesToDate' => ['required', 'date_format:d-m-Y', 'after_or_equal:dutiesFromDate'],
        ], [
            'dutiesFromDate.required' => __('Kies een begindatum.'),
            'dutiesToDate.required' => __('Kies een einddatum.'),
            'dutiesToDate.after_or_equal' => __('De einddatum moet op of na de begindatum liggen.'),
        ]);

        $from = CarbonImmutable::createFromFormat('d-m-Y', $this->dutiesFromDate)->toDateString();
        $to = CarbonImmutable::createFromFormat('d-m-Y', $this->dutiesToDate)->toDateString();
        $path = app(ExportService::class)->dutiesExcel($this->version(), $from, $to);

        return $this->downloadExcel($path, "duties-{$from}-until-{$to}.xlsx");
    }

    public function planningPdf(): StreamedResponse
    {
        $content = app(ExportService::class)->planningPdf($this->version());

        return response()->streamDownload(fn () => print $content, 'match-planning.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function daySchedulePdf(): StreamedResponse
    {
        $this->validateDate();
        $this->validateFooterText();
        $content = app(ExportService::class)->daySchedulePdf($this->version(), $this->date, $this->venueId, $this->footerText);

        return response()->streamDownload(fn () => print $content, "day-schedule-{$this->date}.pdf", ['Content-Type' => 'application/pdf']);
    }

    public function dayScheduleJpg(): StreamedResponse
    {
        $this->validateDate();
        $this->validateFooterText();
        $content = app(ExportService::class)->dayScheduleJpg($this->version(), $this->date, $this->venueId, $this->footerText);

        return response()->streamDownload(fn () => print $content, "day-schedule-{$this->date}.jpg", ['Content-Type' => 'image/jpeg']);
    }

    /** @return array<string, string> Y-m-d => label with the number of home matches */
    public function getMatchDaysProperty(): array
    {
        if (!$this->versionId) {
            return [];
        }

        return $this->version()->matches()
            ->where('match_type', MatchType::Home)
            ->whereNotNull('date')
            ->get(['date'])
            ->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString())
            ->sortKeys()
            ->map(fn ($matches, string $date): string => DateFormat::withWeekday($date).' · '
                .trans_choice(':count wedstrijd|:count wedstrijden', $matches->count()))
            ->all();
    }

    /** @return array<int, string> */
    public function getVenuesProperty(): array
    {
        if (!$this->versionId || !$this->date) {
            return [];
        }

        return $this->version()->matches()
            ->where('match_type', MatchType::Home)
            ->whereDate('date', $this->date)
            ->whereNotNull('venue_id')
            ->with('venue:id,name')
            ->get()
            ->pluck('venue')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function version(): ScheduleVersion
    {
        return ScheduleVersion::findOrFail($this->versionId);
    }

    private function downloadExcel(string $path, string $name): BinaryFileResponse
    {
        return response()->download($path, $name)->deleteFileAfterSend();
    }

    private function validateDate(): void
    {
        $this->validate(['date' => ['required', 'date_format:Y-m-d']]);
    }

    private function validateFooterText(): void
    {
        $this->footerText = trim($this->footerText);
        $this->validate(['footerText' => ['required', 'string', 'max:255']], [
            'footerText.required' => __('Vul een voettekst in.'),
            'footerText.max' => __('De voettekst mag maximaal 255 tekens zijn.'),
        ]);
    }

    private function setDutiesPeriod(): void
    {
        $version = $this->versionId ? ScheduleVersion::find($this->versionId) : null;
        $first = $version?->matches()->whereNotNull('date')->min('date');
        $last = $version?->matches()->whereNotNull('date')->max('date');
        $this->dutiesFromDate = CarbonImmutable::parse($first ?: now())->format('d-m-Y');
        $this->dutiesToDate = CarbonImmutable::parse($last ?: now()->addMonths(3))->format('d-m-Y');
    }

    private function setMatchDate(): void
    {
        $days = array_keys($this->matchDays);
        $todayOrLater = collect($days)->first(fn (string $day): bool => $day >= now()->toDateString());
        $this->date = $todayOrLater ?? ($days[0] ?? now()->toDateString());
    }

    private function setVenue(): void
    {
        $this->venueId = collect($this->venues)->keys()->first();
    }
}
