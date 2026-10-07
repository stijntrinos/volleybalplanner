<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\AuthorizesMatches;
use App\Planner\Filament\Pages\Concerns\ManagesDutyBlocks;
use App\Planner\Filament\Pages\Concerns\ShowsErrors;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\DutyPlanner as DutyPlannerService;
use BackedEnum;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Session;
use UnitEnum;

/** Referee, scorer and hall duty planning (module duties). */
class DutyPlanner extends Page
{
    use AuthorizesMatches,
        HasPlannerPage,
        HasVersionSelection,
        ManagesDutyBlocks,
        ShowsErrors;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 4;

    protected string $view = 'planner::filament.pages.duty-planner';

    protected Width|string|null $maxContentWidth = 'full';

    #[Session]
    public ?int $versionId = null;

    public string $mode = DutyPlannerService::MODE_FILL;

    public string $fromDate;

    public string $toDate;

    /** @var array{matches?: list<array<string, mixed>>, hall_duties?: list<array<string, mixed>>} */
    #[Session]
    public array $draft = [];

    #[Session]
    public ?int $draftVersionId = null;

    /** @var array<string, mixed> */
    public array $validation = [];

    public ?string $checkedAt = null;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::Duties];
    }

    public static function getNavigationLabel(): string
    {
        return __('FTZ-planner');
    }

    public function getTitle(): string
    {
        return __('Fluiten, tellen en zaaldienst');
    }

    public function mount(): void
    {
        // The session survives switching clubs; a version of another club is
        // not visible here (club scope) and is replaced by this club's draft.
        if ($this->versionId === null || !ScheduleVersion::query()->whereKey($this->versionId)->exists()) {
            $this->versionId = $this->editableVersionId();
        }
        if ($this->draftVersionId !== $this->versionId) {
            $this->resetDraft();
        }
        $this->setPeriod();
    }

    public function updatedVersionId(): void
    {
        $this->setPeriod();
        $this->resetDraft();
    }

    public function updatedMode(): void
    {
        $this->resetDraft();
    }

    public function updatedFromDate(): void
    {
        $this->resetDraft();
    }

    public function updatedToDate(): void
    {
        $this->resetDraft();
    }

    public function generate(): void
    {
        $this->validatePeriod();
        [$from, $to] = $this->isoPeriod();
        $mode = in_array($this->mode, [DutyPlannerService::MODE_FILL, DutyPlannerService::MODE_REDISTRIBUTE], true)
            ? $this->mode
            : DutyPlannerService::MODE_FILL;
        $fresh = app(DutyPlannerService::class)->generate(ScheduleVersion::findOrFail($this->versionId), $mode, $from, $to);
        $fresh['matches'] = $this->keepLockedRows($this->draft['matches'] ?? [], $fresh['matches'] ?? []);
        $fresh['hall_duties'] = $this->keepLockedRows($this->draft['hall_duties'] ?? [], $fresh['hall_duties'] ?? []);
        $this->draft = $fresh;
        $this->draftVersionId = $this->versionId;
        $this->validation = [];
        $this->checkedAt = null;
        Notification::make()->title(__('FTZ-concept gegenereerd'))->success()->send();
    }

    public function check(): void
    {
        $this->validation = app(DutyPlannerService::class)->validate(ScheduleVersion::findOrFail($this->versionId), $this->draft);
        $this->checkedAt = now()->format('d-m-Y H:i');
        $count = $this->validation['count'];
        Notification::make()
            ->title($count
                ? trans_choice(':count aandachtspunt gevonden|:count aandachtspunten gevonden', $count)
                : __('Alle FTZ-toewijzingen zijn geldig'))
            ->color($count ? 'warning' : 'success')->send();
    }

    public function updatedDraft(): void
    {
        $this->validation = [];
        $this->checkedAt = null;
    }

    /**
     * The teams that can be chosen as referee, scorer or hall duty. Teams not
     * included in duties are left out; the check would reject them anyway.
     *
     * @return array<string, string>
     */
    public function getTeamOptionsProperty(): array
    {
        if (!$this->versionId) {
            return [];
        }

        return Team::query()
            ->where('include_in_duties', true)
            ->orderBy('code')
            ->pluck('code', 'code')
            ->all();
    }

    /** @return array{matches: int, referees: int, scorers: int, hall_duties: int, issues: int} */
    public function getSummaryProperty(): array
    {
        $matches = collect($this->draft['matches'] ?? []);

        return [
            'matches' => $matches->count(),
            'referees' => $matches->filter(fn (array $row): bool => filled($row['referee'] ?? null))->count(),
            'scorers' => $matches->filter(fn (array $row): bool => filled($row['scorer'] ?? null))->count(),
            'hall_duties' => collect($this->draft['hall_duties'] ?? [])->filter(fn (array $row): bool => filled($row['team_code'] ?? null))->count(),
            'issues' => (int) ($this->validation['count'] ?? 0),
        ];
    }

    public function save(): void
    {
        // Assigning writes into the matches themselves; page access says
        // nothing about that, and a published schedule must stay untouched.
        $this->requireMatchPermission('Update');
        $this->check();
        if (($this->validation['count'] ?? 0) > 0) {
            $this->showError(__('Planning niet opgeslagen'), __('Los eerst de ongeldige FTZ-toewijzingen op.'));

            return;
        }

        try {
            $version = $this->editableVersion();
        } catch (DomainException $exception) {
            $this->showError(__('Planning niet opgeslagen'), $exception->getMessage());

            return;
        }

        [$from, $to] = $this->isoPeriod();
        $this->lockFilledRows();
        app(DutyPlannerService::class)->save($version, $this->draft, auth()->id(), $from, $to);
        Notification::make()->title(__('FTZ-planning toegepast'))->success()->send();
    }

    private function resetDraft(): void
    {
        $this->draft = [];
        $this->draftVersionId = null;
        $this->validation = [];
        $this->checkedAt = null;
    }

    private function setPeriod(): void
    {
        $version = $this->versionId ? ScheduleVersion::find($this->versionId) : null;
        $matches = $version?->matches()->whereNotNull('date');
        $this->fromDate = CarbonImmutable::parse($this->dateValue($matches?->min('date')) ?: now())->format('d-m-Y');
        $this->toDate = CarbonImmutable::parse($this->dateValue($matches?->clone()->max('date')) ?: now()->addMonths(3))->format('d-m-Y');
    }

    /**
     * min() and max() return the raw column value; on a driver that stores the
     * date as datetime that is not Y-m-d and validation would trip over it.
     */
    private function dateValue(mixed $value): ?string
    {
        return filled($value) ? substr((string) $value, 0, 10) : null;
    }

    private function validatePeriod(): void
    {
        $this->validate([
            'fromDate' => ['required', 'date_format:d-m-Y'],
            'toDate' => ['required', 'date_format:d-m-Y', 'after_or_equal:fromDate'],
        ], [
            'fromDate.required' => __('Kies een begindatum.'),
            'toDate.required' => __('Kies een einddatum.'),
            'toDate.after_or_equal' => __('De einddatum moet op of na de begindatum liggen.'),
        ]);
    }

    /** @return array{string, string} */
    private function isoPeriod(): array
    {
        return [
            CarbonImmutable::createFromFormat('d-m-Y', $this->fromDate)->toDateString(),
            CarbonImmutable::createFromFormat('d-m-Y', $this->toDate)->toDateString(),
        ];
    }
}
