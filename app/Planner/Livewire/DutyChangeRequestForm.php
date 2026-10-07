<?php

namespace App\Planner\Livewire;

use App\Planner\Enums\ChangeReason;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\Concerns\AsksContactDetails;
use App\Planner\Livewire\Concerns\ResolvesPublicClub;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Services\ChangeRequestService;
use App\Planner\Support\Security\SubmissionRateLimiter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Public form with which a team asks for a change to an assigned referee, scorer or hall duty. */
class DutyChangeRequestForm extends Component
{
    use AsksContactDetails;
    use ResolvesPublicClub;

    public bool $open = true;

    public ?int $teamId = null;

    public string $dutyKey = '';

    public string $reason = '';

    public string $reasonOther = '';

    public string $explanation = '';

    public string $name = '';

    public string $email = '';

    public bool $submitted = false;

    /** @return list<PlannerFeature> */
    protected function requiredFeatures(): array
    {
        return [PlannerFeature::ChangeRequests, PlannerFeature::Duties];
    }

    public function mount(): void
    {
        $this->open = app(ChangeRequestService::class)->isOpen();
    }

    public function updatedTeamId(): void
    {
        $this->dutyKey = '';
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
        return app(ChangeRequestService::class)->dutyTeams();
    }

    /**
     * @return Collection<int, array{
     *     key: string, type: string, label: string, description: string,
     *     date: string, time: string, location: ?string, match_id: ?int,
     *     match_code: ?string, hall_duty_id: ?int
     * }>
     */
    public function getDutiesProperty(): Collection
    {
        return app(ChangeRequestService::class)->duties($this->teamId ?? 0);
    }

    /** @return list<string> */
    public function getReasonsProperty(): array
    {
        return PlannerSetting::current()->dutyRequestReasons();
    }

    public function submit(): void
    {
        if ($seconds = app(SubmissionRateLimiter::class)->attempt('duty-change-request')) {
            $this->addError('email', __('Te veel verzoeken. Probeer het over :seconds seconden opnieuw.', ['seconds' => $seconds]));

            return;
        }

        if (!app(ChangeRequestService::class)->isOpen()) {
            $this->open = false;

            return;
        }

        $this->validate([
            'teamId' => ['required', 'integer', Rule::in($this->getTeamsProperty()->pluck('id')->all())],
            'dutyKey' => ['required', 'string', Rule::in($this->getDutiesProperty()->pluck('key')->all())],
            'reason' => ['required', 'string', Rule::in($this->getReasonsProperty())],
            'reasonOther' => [Rule::requiredIf($this->reason === ChangeReason::Other->value), 'nullable', 'string', 'max:500'],
            'explanation' => ['required', 'string', 'min:5', 'max:2000'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
            ...$this->contactRules(),
        ], [
            'teamId.required' => __('Kies eerst je team.'),
            'teamId.in' => __('Kies een team uit de lijst.'),
            'dutyKey.required' => __('Kies de FTZ-dienst die je wilt wijzigen.'),
            'dutyKey.in' => __('Kies een FTZ-dienst uit de lijst.'),
            'reason.required' => __('Kies een reden.'),
            'reasonOther.required' => __('Beschrijf de reden.'),
            'explanation.required' => __('Vul een toelichting in.'),
            'explanation.min' => __('Geef iets meer toelichting, zodat wedstrijdzaken het kan beoordelen.'),
            'name.required' => __('Vul je naam in.'),
            'email.required' => __('Vul je e-mailadres in.'),
            'email.email' => __('Vul een geldig e-mailadres in.'),
            ...$this->contactMessages(),
        ]);

        app(ChangeRequestService::class)->submitDuty([
            'team_id' => $this->teamId,
            'duty_key' => $this->dutyKey,
            'name' => trim($this->name),
            'email' => trim($this->email),
            ...$this->contactDetails(),
            'reason' => $this->reason,
            'reason_other' => $this->reason === ChangeReason::Other->value ? trim($this->reasonOther) : null,
            'explanation' => trim($this->explanation),
        ]);

        $this->submitted = true;
    }

    public function submitAnother(): void
    {
        $this->reset(['teamId', 'dutyKey', 'reason', 'reasonOther', 'explanation', 'submitted']);
    }

    public function render(): View
    {
        return view('planner::livewire.duty-change-request-form')
            ->layout('planner::layouts.public', ['noIndex' => true, 'title' => __('FTZ-dienst wijzigen')]);
    }
}
