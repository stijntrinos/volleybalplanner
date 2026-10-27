<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Team;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\Duties\TeamChoice;
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
        'refereeing' => ['refereeing_start', 'refereeing_end'],
    ];

    /**
     * The team choice per role, computed on opening: groups with teams in
     * the order of the planning rules.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    public array $volleystarsChoice = [];

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
                    ->filter(fn ($task): bool => $task->role->value === $role)
                    ->sortBy('sort_order')
                    ->pluck('team_id')
                    ->filter()
                    ->take(3)
                    ->values()
                    ->all();

                return [$role => array_pad($teams, 3, null)];
            })
            ->all();
        $this->volleystarsChoice = $this->volleystarsChoiceFor($block);
    }

    /**
     * Ranks the teams per role the way the duty planning does: proposed, not
     * proposed and cannot, with the standing and the reason in the label.
     * Teams outside the duty planning stay selectable at the bottom.
     *
     * @return array<string, array<string, array<int, string>>>
     */
    private function volleystarsChoiceFor(VolleystarsBlock $block): array
    {
        $context = PlanningContext::forVersion($block->version);
        $block = $context->volleystarsBlocks()->firstWhere('id', $block->id) ?? $block;
        $teamChoice = app(TeamChoice::class);
        $groups = [
            TeamChoice::PROPOSED => __('Voorgesteld'),
            TeamChoice::NOT_PROPOSED => __('Niet voorgesteld'),
            TeamChoice::CANNOT => __('Kan niet'),
        ];
        $choice = [];

        foreach (VolleystarsRole::inDayOrder() as $role) {
            // The slots of this role are being filled in; they do not count.
            foreach (range(0, 2) as $sortOrder) {
                $context->register->remove($context->dutyForVolleystars($block, $role, $sortOrder)['key']);
            }
            $chosen = [];
            foreach ($teamChoice->candidates($context->dutyForVolleystars($block, $role, 0), $context) as $position => $candidate) {
                $label = ($position === 0 && $candidate['status'] === TeamChoice::PROPOSED ? __('Voorstel').': ' : '')
                    .$candidate['code'].' · '.$this->balanceLabel($candidate['balance'], $context->withPoints);
                if ($candidate['status'] !== TeamChoice::PROPOSED && $candidate['reasons'] !== []) {
                    $label .= ' · '.$candidate['reasons'][0];
                }
                $choice[$role->value][$groups[$candidate['status']]][$candidate['team']->id] = $label;
                $chosen[] = $candidate['team']->id;
            }
            foreach ($this->getVolleystarsTeamsProperty()->except($chosen) as $id => $label) {
                $choice[$role->value][__('Buiten de FTZ-planning')][$id] = $label;
            }
            // Count them again after ranking, so the next role sees which team
            // already does this block (V1).
            foreach ($block->tasks->where('role', $role) as $task) {
                if ($task->team) {
                    $context->register->put($context->dutyForVolleystars($block, $role, (int) $task->sort_order, $task->points) + ['team' => $task->team->code]);
                }
            }
        }

        return $choice;
    }

    private function balanceLabel(PointsBalance $balance, bool $withPoints): string
    {
        return match (true) {
            !$withPoints => trans_choice(':count dienst|:count diensten', $balance->total()),
            $balance->hasMinimum() => __(':total van :minimum · nog :remaining · :percentage', [
                'total' => $balance->total(),
                'minimum' => $balance->minimum(),
                'remaining' => $balance->remaining(),
                'percentage' => PointsBalance::percentageText($balance->percentage()),
            ]),
            default => __(':total geleverd · geen minimum', ['total' => $balance->total()]),
        };
    }

    public function closeVolleystars(): void
    {
        $this->reset(['modalVolleystarsId', 'modalVolleystarsTeams', 'modalVolleystarsTimes', 'volleystarsChoice']);
        $this->resetValidation();
    }

    public function saveVolleystars(): void
    {
        $this->requireVolleystarsModule();
        abort_unless(
            auth()->user()?->can('Update:VolleystarsBlock')
            && auth()->user()->can('Update:VolleystarsTask'),
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
                // A duty keeps its points; only a new slot gets the default
                // that applies now.
                $points = $block->tasks()->where('role', $role)->pluck('points', 'sort_order');
                $block->tasks()->where('role', $role)->delete();

                foreach (collect($this->modalVolleystarsTeams[$role] ?? [])->filter()->values() as $sortOrder => $teamId) {
                    $block->tasks()->create([
                        'role' => $role,
                        'team_id' => (int) $teamId,
                        'court_id' => null,
                        'sort_order' => $sortOrder,
                        'points' => $points[$sortOrder] ?? null,
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

    /** @return Collection<int, non-falsy-string> */
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
        return VolleystarsRole::options();
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
