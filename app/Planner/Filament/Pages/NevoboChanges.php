<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\AuthorizesMatches;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ExportService;
use App\Planner\Services\NevoboChangeService;
use App\Planner\Support\DateFormat;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Where our schedule deviates from the Nevobo baseline, so the planning
 * committee can enter the same changes at Nevobo and tick them off.
 *
 * @property-read list<array<string, mixed>> $filteredChanges
 */
class NevoboChanges extends Page
{
    use AuthorizesMatches,
        HasPlannerPage,
        HasVersionSelection;

    public const UNPLANNED = 'unplanned';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::INPUT;

    protected static ?int $navigationSort = 13;

    protected string $view = 'planner::filament.pages.nevobo-changes';

    protected Width|string|null $maxContentWidth = 'full';

    public ?int $versionId = null;

    /** open | processed | all */
    public string $status = 'open';

    /** team | day */
    public string $grouping = 'team';

    /** @var list<array<string, mixed>> NevoboChangeService::forVersion() items */
    public array $changes = [];

    public static function getNavigationLabel(): string
    {
        return __('Nevobo-wijzigingen');
    }

    public function getTitle(): string
    {
        return __('Nevobo-wijzigingen');
    }

    public function mount(): void
    {
        $this->versionId = $this->editableVersionId();
        $this->load();
    }

    public function updatedVersionId(): void
    {
        $this->load();
    }

    /**
     * The changes are applied to a draft, so this page only lists drafts. A
     * published schedule is what the club sees now; you change it via a new
     * draft.
     *
     * @return Collection<int, string>
     */
    public function getVersionsProperty(): Collection
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->latest()->get()
            ->mapWithKeys(fn (ScheduleVersion $version): array => [$version->id => $version->name.' · '.__('concept')]);
    }

    /**
     * At Nevobo you enter all changes of a match in one go -- court, time and
     * date together -- so here too that is one click.
     */
    public function markAll(int $matchId, bool $processed): void
    {
        $this->requireMatchPermission('Update');
        $count = app(NevoboChangeService::class)
            ->markAll($this->matchInVersion($matchId), $processed, auth()->id());
        $this->load();

        Notification::make()
            ->title($processed
                ? trans_choice(':count wijziging doorgevoerd bij Nevobo|:count wijzigingen doorgevoerd bij Nevobo', $count)
                : trans_choice(':count wijziging weer opengezet|:count wijzigingen weer opengezet', $count))
            ->success()->send();
    }

    public function load(): void
    {
        $version = $this->versionId
            ? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->find($this->versionId)
            : null;
        $this->changes = $version ? app(NevoboChangeService::class)->forVersion($version) : [];
    }

    /** @return list<array<string, mixed>> */
    public function getFilteredChangesProperty(): array
    {
        return collect($this->changes)->map(function (array $match): array {
            $match['fields'] = collect($match['fields'])->filter(fn (array $field) => match ($this->status) {
                'open' => !$field['processed'], 'processed' => $field['processed'], default => true,
            })->values()->all();

            return $match;
        })->filter(fn (array $match) => $match['fields'] !== [])->values()->all();
    }

    /** @return array{matches: int, open: int, processed: int, total: int} */
    public function getStatisticsProperty(): array
    {
        $fields = collect($this->changes)->flatMap(fn (array $match): array => $match['fields']);

        return [
            'matches' => count($this->changes),
            'open' => $fields->where('processed', false)->count(),
            'processed' => $fields->where('processed', true)->count(),
            'total' => $fields->count(),
        ];
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'date' => __('Wedstrijddatum'),
            'time' => __('Aanvangstijd'),
            'venue' => __('Sporthal'),
            'court' => __('Veld'),
            default => ucfirst($field),
        };
    }

    public function fieldIcon(string $field): string
    {
        return match ($field) {
            'date' => 'heroicon-o-calendar-days',
            'time' => 'heroicon-o-clock',
            'venue' => 'heroicon-o-building-office-2',
            'court' => 'heroicon-o-square-3-stack-3d',
            default => 'heroicon-o-pencil-square',
        };
    }

    public function formatValue(string $field, mixed $value): string
    {
        if (blank($value)) {
            return __('Niet ingesteld');
        }

        if ($field === 'date') {
            return (string) DateFormat::withWeekday($value, short: true);
        }

        return (string) $value;
    }

    public function formatGroupDate(string $date): string
    {
        return $date === self::UNPLANNED ? __('Ongepland') : (string) DateFormat::withWeekday($date);
    }

    public function exportExcel(): BinaryFileResponse
    {
        $path = app(ExportService::class)->nevoboChangesExcel($this->filteredChanges);

        return response()->download($path, 'nevobo-changes.xlsx')->deleteFileAfterSend();
    }

    public function exportPdf(): StreamedResponse
    {
        $content = app(ExportService::class)->nevoboChangesPdf($this->filteredChanges);

        return response()->streamDownload(fn () => print $content, 'nevobo-changes.pdf', ['Content-Type' => 'application/pdf']);
    }
}
