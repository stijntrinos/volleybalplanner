<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\FixtureMatch;
use App\Planner\Services\ChangeMarkerService;
use App\Planner\Services\PlannerService;
use DomainException;
use Filament\Notifications\Notification;
use Spatie\Activitylog\Models\Activity;

/**
 * Moving matches between venues, courts and times, including undo and Nevobo follow-up.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait MovesMatches
{
    /**
     * The confirmation on a conflict is page state with its own dialog rather
     * than window.confirm: readable, distinguishing a warning from a block,
     * and answerable on a phone.
     *
     * Keys: source ('drag'|'modal'), match_id, venue_id, court_id, time,
     * description, conflicts.
     *
     * @var array<string, mixed>
     */
    public array $confirmation = [];

    public function confirmMove(): void
    {
        $choice = $this->confirmation;
        if (!$choice) {
            return;
        }

        $this->confirmation = [];
        // The edit modal uses the same confirmation, but saves instead of
        // moving; the card was not dragged there.
        if (($choice['source'] ?? 'drag') === 'modal') {
            $this->saveMatch(true);

            return;
        }

        $this->move((int) $choice['match_id'], (int) $choice['venue_id'], (int) $choice['court_id'], (string) $choice['time'], true);
    }

    public function cancelConfirmation(): void
    {
        $source = $this->confirmation['source'] ?? 'drag';
        $this->confirmation = [];
        if ($source === 'modal') {
            // The modal is still open with the entered values; reloading
            // would throw those away while the user wants to adjust them.
            return;
        }
        // The dragged card still sits at its new place; reloading puts it back.
        $this->load();
    }

    public function move(int $matchId, int $venueId, int $courtId, string $time, bool $confirmed = false): void
    {
        $this->requireMatchPermission('Update');
        $match = $this->matchInVersion($matchId);
        try {
            $result = app(PlannerService::class)->move($match, [
                'date' => $this->date, 'time' => $time, 'duration_minutes' => $match->duration_minutes,
                'venue_id' => $venueId, 'court_id' => $courtId, 'second_court_id' => $match->second_court_id,
            ], auth()->id(), $confirmed);
        } catch (DomainException $exception) {
            $this->reportRefused(__('Verplaatsen niet mogelijk'), $exception);

            return;
        }
        if ($result['confirmation_required']) {
            $this->confirmation = [
                'source' => 'drag',
                'match_id' => $matchId, 'venue_id' => $venueId, 'court_id' => $courtId, 'time' => $time,
                'description' => trim($match->home_team_name.' – '.$match->away_team_name, ' –'),
                'conflicts' => array_values($result['conflicts']),
            ];

            return;
        }
        $this->confirmation = [];
        $this->load();
        $this->dispatch('planner-moved', token: $result['undo_token']);
        Notification::make()->title(__('Wedstrijd verplaatst'))->success()->send();
    }

    public function undo(string $token): void
    {
        $this->requireMatchPermission('Update');
        if (app(PlannerService::class)->undo($token)) {
            $this->load();
            Notification::make()->title(__('Verplaatsing ongedaan gemaakt'))->success()->send();
        }
    }

    public function cancelChangeRequest(int $matchId): void
    {
        $this->requireMatchPermission('Update');
        try {
            app(PlannerService::class)->cancelChangeRequest($this->matchInVersion($matchId), auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Annuleren niet mogelijk'), $exception);

            return;
        }
        $this->load();
        Notification::make()->title(__('Wijzigingsverzoek geannuleerd'))->success()->send();
    }

    public function markNevoboApplied(int $matchId): void
    {
        $this->requireMatchPermission('Update');
        try {
            app(PlannerService::class)->markNevoboApplied($this->matchInVersion($matchId), auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Markeren niet mogelijk'), $exception);

            return;
        }
        $this->load();
        Notification::make()->title(__('Wijziging gemarkeerd als doorgevoerd'))->success()->send();
    }

    /** Has to move, but where to is not known yet. */
    public function markToMove(int $matchId): void
    {
        $this->requireMatchPermission('Update');
        try {
            app(ChangeMarkerService::class)->mark($this->matchInVersion($matchId), auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Markeren niet mogelijk'), $exception);

            return;
        }
        if ($this->assistantMatchId === $matchId) {
            $this->closeAssistant();
        }
        $this->load();
        Notification::make()->title(__('Gemarkeerd als te verplaatsen'))
            ->body(__('De wedstrijd staat nu bij de nog te plannen wedstrijden.'))->success()->send();
    }

    public function clearMarker(int $matchId): void
    {
        $this->requireMatchPermission('Update');
        try {
            app(ChangeMarkerService::class)->clear($this->matchInVersion($matchId), auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Markering weghalen niet mogelijk'), $exception);

            return;
        }
        $this->load();
        Notification::make()->title(__('Markering weggehaald'))->success()->send();
    }

    /**
     * The pivot for every refused action on this page. The planner refuses
     * edits on a published version and under an overlap policy that blocks
     * conflicts; without this that came back as a 500 with the dragged card
     * at the wrong place. The reason goes in a modal, not a toast: it does not
     * disappear by itself and the user reads why the action did not go through.
     */
    private function reportRefused(string $title, DomainException $exception): void
    {
        $this->confirmation = [];
        $this->load();
        $this->showError($title, $exception->getMessage());
    }

    /** @return list<array{description: string, moment: ?string, properties: array<string, mixed>}> */
    public function getMoveHistoryProperty(): array
    {
        if (!$this->assistantMatchId) {
            return [];
        }

        return Activity::query()->where('subject_type', (new FixtureMatch)->getMorphClass())->where('subject_id', $this->assistantMatchId)
            ->whereIn('log_name', ['planner-move', 'planner-nevobo'])
            ->latest()->limit(20)->get()->map(fn (Activity $activity): array => [
                'description' => __($activity->description),
                'moment' => $activity->created_at?->format('d-m-Y H:i'),
                'properties' => $activity->properties->toArray(),
            ])->all();
    }
}
