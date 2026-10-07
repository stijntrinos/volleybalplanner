<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityChecker;
use App\Planner\Services\PlannerService;
use App\Planner\Services\ProposalFinder;
use Carbon\CarbonImmutable;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The planning assistant: finding, previewing and storing slot proposals.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait ManagesPlanningAssistant
{
    public ?int $assistantMatchId = null;

    public ?int $assistantVenueId = null;

    public ?int $assistantCourtId = null;

    /** @var list<array<string, mixed>> */
    public array $proposals = [];

    /** A self-chosen moment, as date (Y-m-d) and time (H:i). */
    public string $ownDate = '';

    public string $ownTime = '';

    /** @var array<string, mixed>|null the placement with the outcome of the check */
    public ?array $ownCheck = null;

    public function openAssistant(int $matchId): void
    {
        $this->requireMatchPermission('View');
        $match = $this->matchInVersion($matchId);
        $this->assistantMatchId = $match->id;
        $this->assistantVenueId = $match->venue_id
            ?? Venue::query()->where('is_active', true)->where('for_matches', true)->value('id');
        $this->assistantCourtId = $match->court_id
            ?? Court::query()->where('venue_id', $this->assistantVenueId)->where('is_active', true)->where('is_training_court', false)->value('id');
        $this->proposals = [];
        $this->ownDate = $match->date?->toDateString() ?? '';
        $this->ownTime = substr((string) $match->time, 0, 5);
        $this->ownCheck = null;
        if ($this->assistantVenueId && $this->assistantCourtId) {
            $this->findProposals();
        }
    }

    public function closeAssistant(): void
    {
        $this->reset(['assistantMatchId', 'assistantVenueId', 'assistantCourtId', 'proposals', 'ownDate', 'ownTime', 'ownCheck']);
    }

    public function findProposals(): void
    {
        // Another venue or court makes the previous own check worthless.
        $this->ownCheck = null;
        if (!$this->assistantMatchId || !$this->assistantVenueId || !$this->assistantCourtId) {
            $this->proposals = [];

            return;
        }
        $match = $this->matchInVersion($this->assistantMatchId);
        $this->proposals = app(ProposalFinder::class)->find($match, (int) $this->assistantVenueId, (int) $this->assistantCourtId);
    }

    public function useProposal(int $index): void
    {
        $this->requireMatchPermission('Update');
        $proposal = $this->proposals[$index] ?? null;
        if (!$proposal || !$this->assistantMatchId) {
            return;
        }
        $this->applyPlacement($proposal);
    }

    /**
     * Checks a self-chosen date, time and venue the same way as a proposal:
     * both teams, the venue, trainings and external matches. Unlike the
     * proposals this also shows a red outcome, with the reasons, so you know
     * why it cannot be done.
     */
    public function checkOwnSlot(): void
    {
        $this->requireMatchPermission('View');
        if (!$this->assistantMatchId) {
            return;
        }
        $this->validate([
            'ownDate' => ['required', 'date_format:Y-m-d'],
            'ownTime' => ['required', 'date_format:H:i'],
            'assistantVenueId' => ['required', 'integer'],
            'assistantCourtId' => ['required', 'integer'],
        ], [
            'ownDate.required' => __('Kies een datum.'),
            'ownDate.date_format' => __('Kies een geldige datum.'),
            'ownTime.required' => __('Kies een tijd.'),
            'ownTime.date_format' => __('Vul de tijd in als uu:mm.'),
            'assistantVenueId.required' => __('Kies een sporthal.'),
            'assistantCourtId.required' => __('Kies een veld.'),
        ]);

        $match = $this->matchInVersion($this->assistantMatchId);
        $placement = [
            'date' => CarbonImmutable::createFromFormat('Y-m-d', $this->ownDate)->toDateString(),
            'time' => $this->ownTime,
            'duration_minutes' => $match->duration_minutes,
            'venue_id' => (int) $this->assistantVenueId,
            'court_id' => (int) $this->assistantCourtId,
        ];

        $this->ownCheck = $placement + [
            'check' => app(AvailabilityChecker::class)->check($match, $placement),
        ];
    }

    /**
     * Applies the checked own moment. On a conflict the user has already seen
     * the reasons and confirmed; the overlap policy can still refuse it.
     */
    public function useOwnSlot(): void
    {
        $this->requireMatchPermission('Update');
        if (!$this->ownCheck || !$this->assistantMatchId) {
            return;
        }
        $this->applyPlacement($this->ownCheck, confirmed: true);
    }

    public function saveOwnSlot(): void
    {
        $this->requireMatchPermission('Update');
        if (!$this->ownCheck || !$this->assistantMatchId) {
            return;
        }
        $this->savePlacement($this->ownCheck);
    }

    /** @param array<string, mixed> $placement */
    private function applyPlacement(array $placement, bool $confirmed = false): void
    {
        $match = $this->matchInVersion($this->assistantMatchId);
        try {
            $result = app(PlannerService::class)->move($match, [
                'date' => $placement['date'], 'time' => $placement['time'],
                'duration_minutes' => $match->duration_minutes, 'venue_id' => $placement['venue_id'],
                'court_id' => $placement['court_id'], 'second_court_id' => null,
            ], auth()->id(), $confirmed);
        } catch (DomainException $exception) {
            $this->reportRefused(__('Voorstel toepassen niet mogelijk'), $exception);

            return;
        }
        // Without confirmation the planner writes nothing on a conflict; then
        // there should be no message that it worked either.
        if ($result['confirmation_required']) {
            $this->showError(__('Voorstel niet toegepast'), __('Dit moment geeft een conflict. Controleer het opnieuw.'));

            return;
        }
        $this->date = $placement['date'];
        $this->savePlannerPreference();
        $this->closeAssistant();
        $this->load();
        Notification::make()->title(__('Voorstel toegepast'))->success()->send();
    }

    public function saveProposal(int $index): void
    {
        $this->requireMatchPermission('Update');
        $proposal = $this->proposals[$index] ?? null;
        if (!$proposal || !$this->assistantMatchId) {
            return;
        }
        $this->savePlacement($proposal);
    }

    /** @param array<string, mixed> $proposal */
    private function savePlacement(array $proposal): void
    {
        $match = $this->matchInVersion($this->assistantMatchId);
        try {
            app(PlannerService::class)->saveProposal($match, [
                'date' => $proposal['date'],
                'time' => $proposal['time'],
                'duration_minutes' => $match->duration_minutes,
                'venue_id' => $proposal['venue_id'],
                'court_id' => $proposal['court_id'],
            ], auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Voorstel opslaan niet mogelijk'), $exception);

            return;
        }
        Notification::make()->title(__('Voorstel opgeslagen'))->body(__('De huidige planning is niet gewijzigd.'))->success()->send();
    }

    public function deleteProposal(): void
    {
        $this->requireMatchPermission('Update');
        if (!$this->assistantMatchId) {
            return;
        }
        try {
            app(PlannerService::class)->cancelProposal($this->matchInVersion($this->assistantMatchId), auth()->id());
        } catch (DomainException $exception) {
            $this->reportRefused(__('Voorstel verwijderen niet mogelijk'), $exception);

            return;
        }
        Notification::make()->title(__('Opgeslagen voorstel verwijderd'))->success()->send();
    }

    /** @return Collection<int, string> */
    public function getAssistantVenuesProperty(): Collection
    {
        return Venue::query()->where('is_active', true)->where('for_matches', true)->inPlanningOrder()->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function getAssistantCourtsProperty(): Collection
    {
        return Court::query()->when($this->assistantVenueId, fn ($query) => $query->where('venue_id', $this->assistantVenueId))
            ->where('is_active', true)->where('is_training_court', false)->orderBy('sort_order')->pluck('name', 'id');
    }

    /** @return array{date: ?string, time: string, venue: ?string, court: ?string, duration: ?int}|null */
    public function getSavedProposalProperty(): ?array
    {
        if (!$this->assistantMatchId) {
            return null;
        }
        $match = FixtureMatch::query()->with('suggestedCourt')->where('version_id', $this->versionId)->find($this->assistantMatchId);
        if (!$match || (!$match->away_new_date && !$match->away_new_time && !$match->away_new_venue)) {
            return null;
        }

        return [
            'date' => $match->away_new_date?->toDateString(),
            'time' => substr((string) $match->away_new_time, 0, 5),
            'venue' => $match->away_new_venue,
            'court' => $match->suggestedCourt?->name,
            'duration' => $match->suggested_duration_minutes,
        ];
    }
}
