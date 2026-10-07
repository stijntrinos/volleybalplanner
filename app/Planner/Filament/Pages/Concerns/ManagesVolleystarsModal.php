<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Team;
use App\Planner\Models\VolleystarsBlock;
use App\Support\Tenancy\CurrentClub;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * State and actions for assigning Volleystars tasks to a block. Only
 * available when the club has the volleystars module.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait ManagesVolleystarsModal
{
    public ?int $modalVolleystarsId = null;

    /** @var array<string, list<int|null>> team ids per role value, three slots each */
    public array $modalVolleystarsTeams = [];

    /** @var array<string, array{start: string, end: string}> per role value */
    public array $modalVolleystarsTimes = [];

    /** Block columns per role: [start, end]. */
    private const VOLLEYSTARS_COLUMNS = [
        'setup' => ['setup_start', 'setup_end'],
        'teardown' => ['teardown_start', 'teardown_end'],
        'scoring' => ['scoring_start', 'scoring_end'],
    ];

    public function openVolleystars(int $blockId): void
    {
        $this->requireVolleystarsModule();
        abort_unless(auth()->user()?->can('View:VolleystarsBlock'), 403);

        $block = VolleystarsBlock::query()
            ->with('tasks')
            ->where('version_id', $this->versionId)
            ->findOrFail($blockId);

        $this->modalVolleystarsId = $block->id;
        $this->modalVolleystarsTimes = collect(self::VOLLEYSTARS_COLUMNS)
            ->map(fn (array $columns): array => [
                'start' => substr((string) $block->{$columns[0]}, 0, 5),
                'end' => substr((string) $block->{$columns[1]}, 0, 5),
            ])
            ->all();
        $this->modalVolleystarsTeams = collect($this->volleystarsRoles)
            ->mapWithKeys(function (string $label, string $role) use ($block): array {
                $teams = $block->tasks
                    ->filter(fn ($task): bool => $task->role?->value === $role)
                    ->sortBy('sort_order')
                    ->pluck('team_id')
                    ->filter()
                    ->take(3)
                    ->values()
                    ->all();

                return [$role => array_pad($teams, 3, null)];
            })
            ->all();
    }

    public function closeVolleystars(): void
    {
        $this->reset(['modalVolleystarsId', 'modalVolleystarsTeams', 'modalVolleystarsTimes']);
        $this->resetValidation();
    }

    public function saveVolleystars(): void
    {
        $this->requireVolleystarsModule();
        abort_unless(
            auth()->user()?->can('Update:VolleystarsBlock')
            && auth()->user()?->can('Update:VolleystarsTask'),
            403,
        );

        $block = VolleystarsBlock::query()
            ->where('version_id', $this->versionId)
            ->findOrFail($this->modalVolleystarsId);

        if (!$block->version->isDraft()) {
            Notification::make()->title(__('Deze planning is alleen-lezen'))->danger()->send();

            return;
        }

        $this->validate([
            'modalVolleystarsTeams' => ['required', 'array'],
            'modalVolleystarsTeams.*' => ['array', 'max:3'],
            'modalVolleystarsTeams.*.*' => ['nullable', 'integer'],
            'modalVolleystarsTimes' => ['required', 'array'],
            'modalVolleystarsTimes.*.start' => ['required', 'date_format:H:i'],
            'modalVolleystarsTimes.*.end' => ['required', 'date_format:H:i'],
        ]);

        $teamIds = collect($this->modalVolleystarsTeams)->flatten()->filter()->map(fn ($id): int => (int) $id)->unique();
        $validTeamIds = Team::query()->whereKey($teamIds)->pluck('id');
        if ($validTeamIds->count() !== $teamIds->count()) {
            $this->addError('modalVolleystarsTeams', __('Een van de gekozen teams bestaat niet binnen deze vereniging.'));

            return;
        }

        foreach ($this->volleystarsRoles as $role => $label) {
            $times = $this->modalVolleystarsTimes[$role] ?? [];
            if (($times['end'] ?? '') <= ($times['start'] ?? '')) {
                $this->addError("modalVolleystarsTimes.{$role}.end", __('De eindtijd van :task moet na de begintijd liggen.', ['task' => $label]));

                return;
            }
        }

        DB::transaction(function () use ($block): void {
            $times = [];
            foreach (self::VOLLEYSTARS_COLUMNS as $role => [$start, $end]) {
                $times[$start] = $this->modalVolleystarsTimes[$role]['start'];
                $times[$end] = $this->modalVolleystarsTimes[$role]['end'];
            }
            $block->update($times);

            foreach (array_keys($this->volleystarsRoles) as $role) {
                $block->tasks()->where('role', $role)->delete();

                foreach (collect($this->modalVolleystarsTeams[$role] ?? [])->filter()->values() as $sortOrder => $teamId) {
                    $block->tasks()->create([
                        'role' => $role,
                        'team_id' => (int) $teamId,
                        'court_id' => null,
                        'sort_order' => $sortOrder,
                    ]);
                }
            }
        });

        activity('planner-planning')->performedOn($block)->causedBy(auth()->id())
            ->log('Volleystars tasks updated from home matches');

        $this->closeVolleystars();
        $this->load();
        Notification::make()->title(__('VolleyStars-taken opgeslagen'))->success()->send();
    }

    /** @return Collection<int, string> */
    public function getVolleystarsTeamsProperty(): Collection
    {
        return Team::query()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Team $team): array => [$team->id => "{$team->code} · {$team->name}"]);
    }

    /** @return array<string, string> role value => label */
    public function getVolleystarsRolesProperty(): array
    {
        return collect([VolleystarsRole::Setup, VolleystarsRole::Teardown, VolleystarsRole::Scoring])
            ->mapWithKeys(fn (VolleystarsRole $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    public function getModalVolleystarsBlockProperty(): ?VolleystarsBlock
    {
        return $this->modalVolleystarsId
            ? VolleystarsBlock::query()->with('venue')->where('version_id', $this->versionId)->find($this->modalVolleystarsId)
            : null;
    }

    private function requireVolleystarsModule(): void
    {
        abort_unless(app(CurrentClub::class)->get()?->hasFeature(PlannerFeature::Volleystars), 404);
    }
}
