<?php

namespace App\Planner\Filament\Pages;

use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Concerns\HasPlannerPage;
use App\Planner\Filament\Concerns\HasVersionSelection;
use App\Planner\Filament\NavigationGroups;
use App\Planner\Filament\Pages\Concerns\AuthorizesMatches;
use App\Planner\Filament\Pages\Concerns\ShowsErrors;
use App\Planner\Models\FixtureMatch;
use App\Planner\Services\AwayMatchService;
use App\Support\Tenancy\CurrentClub;
use BackedEnum;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use UnitEnum;

/** @property-read LengthAwarePaginator<int, FixtureMatch> $matches */
class AwayMatches extends Page
{
    use AuthorizesMatches,
        HasPlannerPage,
        HasVersionSelection,
        ShowsErrors,
        WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTopRightOnSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroups::PLANNING;

    protected static ?int $navigationSort = 2;

    protected string $view = 'planner::filament.pages.away-matches';

    protected Width|string|null $maxContentWidth = 'full';

    public ?int $versionId = null;

    public string $search = '';

    public string $status = 'all';

    public ?int $editMatchId = null;

    /** @var array<string, mixed> */
    public array $editing = [];

    public int $perPage = 25;

    protected static function requiredFeatures(): array
    {
        return [PlannerFeature::AwayMatches];
    }

    public static function getNavigationLabel(): string
    {
        return __('Uitwedstrijden');
    }

    public function getTitle(): string
    {
        return __('Uitwedstrijden');
    }

    public function mount(): void
    {
        $this->versionId = $this->editableVersionId();
    }

    public function getSubheading(): ?string
    {
        return __('Overzicht van wedstrijden waarbij :club de uitspelende partij is.', [
            'club' => app(CurrentClub::class)->get()?->name,
        ]);
    }

    public function getMatchesProperty(): LengthAwarePaginator
    {
        return FixtureMatch::query()
            ->where('version_id', $this->versionId)
            ->where('match_type', MatchType::Away)
            ->when($this->status !== 'all', fn ($query) => $query->where('away_change_status', $this->status))
            ->when(trim($this->search) !== '', function ($query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn ($query) => $query->where('match_code', 'like', $search)
                    ->orWhere('home_team_name', 'like', $search)->orWhere('away_team_name', 'like', $search));
            })
            ->with('venue')->orderBy('date')->orderBy('time')->paginate($this->perPage);
    }

    /**
     * The schedule of all dates shown is loaded in one query instead of one
     * per row.
     *
     * @return array<int, list<string>>
     */
    public function getConflictsProperty(): array
    {
        $service = app(AwayMatchService::class);
        $matches = $this->matches->getCollection();
        $dates = $matches
            ->flatMap(fn (FixtureMatch $match): array => [
                $match->date?->toDateString(),
                $match->away_new_date?->toDateString(),
            ])
            ->filter()->unique()->values();

        $schedule = $dates->isEmpty()
            ? collect()
            : FixtureMatch::query()->where('version_id', $this->versionId)->whereIn('date', $dates)->get();

        return $matches->mapWithKeys(fn (FixtureMatch $match): array => [
            $match->id => $service->conflicts($match, $schedule),
        ])->all();
    }

    public function updatedVersionId(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function edit(int $matchId): void
    {
        $this->requireMatchPermission('Update');
        $match = $this->matchInVersion($matchId);
        $this->editMatchId = $match->id;
        $this->editing = [
            'status' => $match->away_change_status?->value,
            'date' => $match->away_new_date?->format('d-m-Y'),
            'time' => substr((string) $match->away_new_time, 0, 5) ?: null,
            'venue' => $match->away_new_venue,
            'notes' => $match->away_notes,
        ];
    }

    public function closeEdit(): void
    {
        $this->reset(['editMatchId', 'editing']);
    }

    public function save(): void
    {
        $this->requireMatchPermission('Update');
        $this->validate([
            'editing.status' => ['required', 'in:none,in_progress,final,'.AwayMatchService::STATUS_REJECTED],
            'editing.date' => ['nullable', 'date_format:d-m-Y'],
            'editing.time' => ['nullable', 'date_format:H:i'],
            'editing.venue' => ['nullable', 'string', 'max:255'],
            'editing.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->editableVersion();
            app(AwayMatchService::class)->update($this->matchInVersion((int) $this->editMatchId), [
                'away_change_status' => $this->editing['status'],
                'away_new_date' => filled($this->editing['date'] ?? null)
                    ? CarbonImmutable::createFromFormat('d-m-Y', $this->editing['date'])->toDateString()
                    : null,
                'away_new_time' => ($this->editing['time'] ?? null) ?: null,
                'away_new_venue' => ($this->editing['venue'] ?? null) ?: null,
                'away_notes' => ($this->editing['notes'] ?? null) ?: null,
            ], auth()->id());
        } catch (DomainException $exception) {
            $this->showError(__('Opslaan niet mogelijk'), $exception->getMessage());

            return;
        }

        $this->closeEdit();
        Notification::make()->title(__('Uitwedstrijd bijgewerkt'))->success()->send();
    }
}
