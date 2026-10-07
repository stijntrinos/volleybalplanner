<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\CleansUpImports;
use App\Planner\Filament\Pages\Concerns\HandlesImportFiles;
use App\Planner\Filament\Pages\Concerns\ImportsAvailability;
use App\Planner\Filament\Pages\Concerns\ImportsDuties;
use App\Planner\Filament\Pages\Concerns\ImportsTrainings;
use App\Planner\Filament\Pages\Concerns\ShowsErrors;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ImportService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Imports: the Nevobo match file (new draft or update of a draft, with a
 * choice per conflicting or removed match), trainings, availability, duties
 * and the cleanup of imported data.
 *
 * @property-read Schema $form
 */
class ImportPlanning extends Page implements HasForms
{
    use CleansUpImports,
        HandlesImportFiles,
        HasPlannerPage,
        ImportsAvailability,
        ImportsDuties,
        ImportsTrainings,
        InteractsWithForms,
        ShowsErrors;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::INPUT;

    protected static ?int $navigationSort = 11;

    protected string $view = 'planner::filament.pages.import-planning';

    protected Width|string|null $maxContentWidth = 'full';

    public const MODE_NEW = 'new';

    public const MODE_UPDATE = 'update';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null ImportService::dryRun() result */
    public ?array $report = null;

    /** @var array<string, mixed>|null ImportService::verify() result */
    public ?array $verification = null;

    /** @var array<int, string> choice per match id for conflicts and removed matches */
    public array $importChoices = [];

    private ?array $toChooseCache = null;

    public string $section = 'matches';

    public static function getNavigationLabel(): string
    {
        return __('Planning importeren');
    }

    public function getTitle(): string
    {
        return __('Planning importeren');
    }

    public function mount(): void
    {
        $this->form->fill(['mode' => self::MODE_NEW]);
        $this->trainingForm->fill();
        $this->availabilityForm->fill();
        $this->dutyForm->fill(['overwrite' => false]);
        $this->refreshCleanupOverview();
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> section key => [label, description, icon] */
    public function sections(): array
    {
        return array_filter([
            'matches' => [__('Planning importeren'), __('Wedstrijden uit Nevobo'), 'heroicon-o-calendar-days'],
            'trainings' => $this->hasModule(PlannerFeature::Trainings)
                ? [__('Trainingen importeren'), __('Trainingsschema bijwerken'), 'heroicon-o-academic-cap'] : null,
            'availability' => [__('Beschikbaarheid importeren'), __('Zaalhuur van de gemeente'), 'heroicon-o-building-office-2'],
            'duties' => $this->hasModule(PlannerFeature::Duties)
                ? [__('Diensten importeren'), __('Fluit-, tel- en zaaldiensten'), 'heroicon-o-megaphone'] : null,
            'cleanup' => [__('Importgegevens opschonen'), __('Oude brongegevens verwijderen'), 'heroicon-o-trash'],
        ]);
    }

    public function updatedSection(string $value): void
    {
        if (!array_key_exists($value, $this->sections())) {
            $this->section = 'matches';
        }
    }

    public function updatedData(): void
    {
        $this->report = null;
        $this->verification = null;
        $this->importChoices = [];
        $this->toChooseCache = null;
    }

    /**
     * Conflicts and removed matches used to be handled silently: the service
     * could already do it per match, but nobody was ever asked.
     *
     * @return array{conflict: list<array{match: FixtureMatch, row: array<string, mixed>}>, removed: list<array{match: FixtureMatch}>}
     */
    public function getToChooseProperty(): array
    {
        // The view touches this property several times per render.
        return $this->toChooseCache ??= $this->computeToChoose();
    }

    private function computeToChoose(): array
    {
        $categories = $this->report['categories'] ?? [];
        $conflicts = $categories[ImportService::CATEGORY_CONFLICT] ?? [];
        $removed = $categories[ImportService::CATEGORY_REMOVED] ?? [];
        $ids = array_merge(array_column($conflicts, 'match_id'), array_column($removed, 'match_id'));
        $matches = FixtureMatch::query()->whereKey($ids)->with(['venue', 'court'])->get()->keyBy('id');

        return [
            'conflict' => collect($conflicts)
                ->map(fn (array $item): ?array => ($match = $matches->get($item['match_id']))
                    ? ['match' => $match, 'row' => $item['row']] : null)
                ->filter()->values()->all(),
            'removed' => collect($removed)
                ->map(fn (array $item): ?array => ($match = $matches->get($item['match_id']))
                    ? ['match' => $match] : null)
                ->filter()->values()->all(),
        ];
    }

    public function chooseForAll(string $category, string $choice): void
    {
        $allowed = [
            'conflict' => [ImportService::CHOICE_SOURCE, ImportService::CHOICE_MANUAL],
            'removed' => [ImportService::CHOICE_KEEP, ImportService::CHOICE_DELETE],
        ];
        if (!in_array($choice, $allowed[$category] ?? [], true)) {
            return;
        }

        foreach ($this->toChoose[$category] ?? [] as $item) {
            $this->importChoices[$item['match']->id] = $choice;
        }
    }

    /** @return array<int, string> */
    private function defaultChoices(array $categories): array
    {
        $choices = [];
        foreach ($categories[ImportService::CATEGORY_CONFLICT] ?? [] as $item) {
            $choices[$item['match_id']] = ImportService::CHOICE_MANUAL;
        }
        foreach ($categories[ImportService::CATEGORY_REMOVED] ?? [] as $item) {
            $choices[$item['match_id']] = ImportService::CHOICE_KEEP;
        }

        return $choices;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('1. Bronbestand en importdoel'))
                ->description(__('Upload het Nevobo-bestand en kies of je een nieuwe conceptversie maakt of een bestaande bijwerkt.'))
                ->components([
                    $this->importFileUpload('file', __('Nevobo-importbestand'), 'matches')
                        ->helperText(__('Ondersteunde bestanden: Excel (.xlsx) en CSV.'))
                        ->columnSpanFull(),
                    Select::make('mode')->label(__('Importmodus'))->options([
                        self::MODE_NEW => __('Nieuwe conceptversie'),
                        self::MODE_UPDATE => __('Bestaande conceptversie bijwerken'),
                    ])->live()->required(),
                    TextInput::make('name')->label(__('Naam nieuwe versie'))->maxLength(255)
                        ->required(fn ($get) => $get('mode') === self::MODE_NEW)
                        ->visible(fn ($get) => $get('mode') === self::MODE_NEW),
                    Select::make('version_id')->label(__('Conceptversie'))
                        ->options(fn () => ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->pluck('name', 'id'))
                        ->required(fn ($get) => $get('mode') === self::MODE_UPDATE)
                        ->visible(fn ($get) => $get('mode') === self::MODE_UPDATE),
                ])->columns(2),
        ])->statePath('data');
    }

    public function analyze(): void
    {
        $data = $this->form->getState();
        $path = $this->importPath($data['file'], 'matches');
        $this->attempt(__('Importbestand is niet geldig'), function () use ($data, $path): void {
            $version = $this->targetVersion($data);
            $this->report = app(ImportService::class)->dryRun($path, $version);
            $this->toChooseCache = null;
            $this->importChoices = $this->defaultChoices($this->report['categories'] ?? []);
            Notification::make()->title(__('Dry-run voltooid'))->success()->send();
        });
    }

    public function import(): void
    {
        $data = $this->form->getState();
        $path = $this->importPath($data['file'], 'matches');
        $this->attempt(__('Importeren mislukt'), function () use ($data, $path): void {
            $service = app(ImportService::class);
            if ($version = $this->targetVersion($data)) {
                $service->updateVersion($path, $version, $this->importChoices);
            } else {
                $version = $service->importNewVersion($path, $data['name'], auth()->id());
            }
            $this->verification = $service->verify($path, $version);
            $this->report = null;
            $this->toChooseCache = null;
            $this->importChoices = [];
            $this->deleteImportFile($data['file']);
            Notification::make()->title(__('Planning geïmporteerd'))
                ->body(__('Versie: :name.', ['name' => $version->name]).' '.($this->verification['equal']
                    ? __('Database komt overeen met het bronbestand.')
                    : __(':count verificatieverschillen gevonden.', ['count' => count($this->verification['differences'])])))
                ->color($this->verification['equal'] ? 'success' : 'warning')->send();
        });
    }

    /** Updating only ever targets a draft of this club. */
    private function targetVersion(array $data): ?ScheduleVersion
    {
        return ($data['mode'] ?? self::MODE_NEW) === self::MODE_UPDATE
            ? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->findOrFail($data['version_id'])
            : null;
    }

    /** Shared upload field: per club, size and type limited. */
    protected function importFileUpload(string $name, string $label, string $kind): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk('local')
            ->directory(fn (): string => $this->importDirectory($kind))
            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
            ->maxSize(self::MAX_UPLOAD_KB)
            ->required();
    }
}
