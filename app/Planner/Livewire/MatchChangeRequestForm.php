<?php

namespace App\Planner\Livewire;

use App\Planner\Enums\ChangeReason;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\Concerns\AsksContactDetails;
use App\Planner\Livewire\Concerns\ResolvesPublicClub;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Services\ChangeRequestService;
use App\Planner\Support\Security\SubmissionRateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * The form behind the shared link. The contact person picks the team, then
 * the match, and then fills in the reason. The page is in no menu; the link
 * is the only way in.
 */
class MatchChangeRequestForm extends Component
{
    use AsksContactDetails;
    use ResolvesPublicClub;

    public bool $open = true;

    public ?int $teamId = null;

    public ?int $matchId = null;

    public string $reason = '';

    public string $reasonOther = '';

    public string $explanation = '';

    /** An own proposal for the new moment; optional. */
    public string $proposedDate = '';

    public string $proposedTime = '';

    public string $name = '';

    public string $email = '';

    public bool $submitted = false;

    /** @return list<PlannerFeature> */
    protected function requiredFeatures(): array
    {
        return [PlannerFeature::ChangeRequests];
    }

    public function mount(): void
    {
        $this->open = app(ChangeRequestService::class)->isOpen();
    }

    /** Another team means another match list; the old choice no longer makes sense. */
    public function updatedTeamId(): void
    {
        $this->matchId = null;
    }

    public function updatedReason(): void
    {
        if ($this->reason !== ChangeReason::Other->value) {
            $this->reasonOther = '';
        }
    }

    /** @return Collection<int, Team> */
    public function getTeamsProperty(): Collection
    {
        return app(ChangeRequestService::class)->teams();
    }

    /** @return Collection<int, FixtureMatch> */
    public function getMatchesProperty(): Collection
    {
        return $this->teamId
            ? app(ChangeRequestService::class)->matches($this->teamId)
            : collect();
    }

    /** @return list<string> */
    public function getReasonsProperty(): array
    {
        return PlannerSetting::current()->requestReasons();
    }

    public function submit(): void
    {
        if ($seconds = app(SubmissionRateLimiter::class)->attempt('match-change-request')) {
            $this->addError('email', __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', ['seconds' => $seconds]));

            return;
        }

        // Checked here as well and not only in the view: the form can be open
        // in a tab while the committee closes it.
        if (!app(ChangeRequestService::class)->isOpen()) {
            $this->open = false;

            return;
        }

        $this->validate([
            'teamId' => ['required', 'integer', Rule::in($this->getTeamsProperty()->pluck('id')->all())],
            'matchId' => ['required', 'integer', Rule::in($this->getMatchesProperty()->pluck('id')->all())],
            'reason' => ['required', 'string', Rule::in($this->getReasonsProperty())],
            'reasonOther' => [Rule::requiredIf($this->reason === ChangeReason::Other->value), 'nullable', 'string', 'max:500'],
            'explanation' => ['required', 'string', 'min:5', 'max:2000'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            'proposedDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'proposedTime' => ['nullable', 'date_format:H:i'],
            ...$this->contactRules(),
        ], [
            'teamId.required' => __('Kies eerst je team.'),
            'teamId.in' => __('Kies een team uit de lijst.'),
            'matchId.required' => __('Kies de wedstrijd die je wilt verzetten.'),
            'matchId.in' => __('Kies een wedstrijd uit de lijst.'),
            'reason.required' => __('Kies een reden.'),
            'reasonOther.required' => __('Beschrijf de reden.'),
            'explanation.required' => __('Vul een toelichting in.'),
            'explanation.min' => __('Geef iets meer toelichting, zodat wedstrijdzaken het kan beoordelen.'),
            'name.required' => __('Vul je naam in.'),
            'email.required' => __('Vul je e-mailadres in.'),
            'email.email' => __('Vul een geldig e-mailadres in.'),
            'proposedDate.date_format' => __('Kies een geldige datum.'),
            'proposedDate.after_or_equal' => __('Een voorgestelde datum kan niet in het verleden liggen.'),
            'proposedTime.date_format' => __('Vul de tijd in als uu:mm, bijvoorbeeld 19:30.'),
            ...$this->contactMessages(),
        ]);

        app(ChangeRequestService::class)->submit([
            'team_id' => $this->teamId,
            'match_id' => $this->matchId,
            'name' => trim($this->name),
            'email' => trim($this->email),
            ...$this->contactDetails(),
            'proposed_date' => $this->proposedDate ?: null,
            'proposed_time' => $this->proposedTime ?: null,
            'reason' => $this->reason,
            'reason_other' => $this->reason === ChangeReason::Other->value ? trim($this->reasonOther) : null,
            'explanation' => trim($this->explanation),
        ]);

        $this->submitted = true;
    }

    public function submitAnother(): void
    {
        $this->reset(['teamId', 'matchId', 'reason', 'reasonOther', 'explanation', 'proposedDate', 'proposedTime', 'submitted']);
    }

    public function render(): View
    {
        // The page is in no menu and does not belong in search engines either.
        return view('planner::livewire.match-change-request-form')
            ->layout('planner::layouts.public', ['noIndex' => true, 'title' => __('Wedstrijd verzetten')]);
    }
}
