<?php

namespace App\Livewire;

use App\Enums\ContactTopic;
use App\Models\Club;
use App\Models\ContactRequest;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The pop-up behind every locked module and every limit in the club panel:
 * which plan unlocks it, and a button that asks us for the upgrade. Opened
 * with a window event: planner-upgrade {feature} or {limit}.
 */
class PlanUpgradePrompt extends Component
{
    public const MODAL = 'plan-upgrade';

    public ?string $feature = null;

    public ?string $limit = null;

    public bool $requested = false;

    #[Locked]
    public ?int $clubId = null;

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $this->clubId = $tenant instanceof Club ? (int) $tenant->getKey() : null;
    }

    public function show(?string $feature = null, ?string $limit = null): void
    {
        $this->feature = PlannerFeature::tryFrom((string) $feature)?->value;
        $this->limit = $this->feature ? null : PlanLimit::tryFrom((string) $limit)?->value;
        $this->requested = false;

        $this->dispatch('open-modal', id: self::MODAL);
    }

    public function requestUpgrade(): void
    {
        $club = $this->club();
        $user = auth()->user();

        if ($club === null || !$user instanceof User) {
            return;
        }

        // One request per club per hour is plenty; a double click sends one.
        if (RateLimiter::tooManyAttempts('plan-upgrade:'.$club->id, 1)) {
            $this->requested = true;

            return;
        }
        RateLimiter::hit('plan-upgrade:'.$club->id, 3600);

        $target = $this->targetPlan($club);
        $request = ContactRequest::create([
            'topic' => ContactTopic::Upgrade,
            'name' => $user->name,
            'club' => $club->name,
            'email' => $user->email,
            'teams' => null,
            'venues' => null,
            'message' => implode("\n", array_filter([
                'Huidig pakket: '.($club->planName() ?? 'geen'),
                'Gewenst pakket: '.($target?->name ?? 'onbekend'),
                'Aanleiding: '.$this->subject(),
                'Werkruimte: '.Filament::getPanel('club')->getUrl($club),
            ])),
        ]);

        if (filled($recipient = config('site.contact_recipient'))) {
            Notification::route('mail', $recipient)->notify(new ContactRequestReceived($request));
        }

        $this->requested = true;
    }

    public function render(): View
    {
        $club = $this->club();
        $feature = PlannerFeature::tryFrom((string) $this->feature);
        $limit = PlanLimit::tryFrom((string) $this->limit);

        return view('livewire.plan-upgrade-prompt', [
            'club' => $club,
            'module' => $feature,
            'cap' => $limit,
            'limitMessage' => $limit && $club?->planLimit($limit) !== null ? $limit->reachedMessage($club->planLimit($limit)) : null,
            'subject' => $this->subject(),
            'target' => $club ? $this->targetPlan($club) : null,
            'replyDays' => config('site.reply_within_days'),
        ]);
    }

    private function subject(): string
    {
        $feature = PlannerFeature::tryFrom((string) $this->feature);
        $limit = PlanLimit::tryFrom((string) $this->limit);

        return $feature?->getLabel() ?? ($limit ? 'meer '.mb_strtolower($limit->getLabel()) : 'een groter pakket');
    }

    private function targetPlan(Club $club): ?Plan
    {
        $feature = PlannerFeature::tryFrom((string) $this->feature);
        $limit = PlanLimit::tryFrom((string) $this->limit);
        $need = $feature ?? $limit;

        if ($need === null) {
            return $club->plan ? $club->plan->nextPlan() : Plan::query()->ordered()->first();
        }

        $needed = $limit ? ($club->planLimit($limit) ?? 0) + 1 : 0;

        return $club->plan ? $club->plan->nextPlanWith($need, $needed) : Plan::firstWith($need, $needed);
    }

    private function club(): ?Club
    {
        $club = $this->clubId ? Club::query()->with('plan')->find($this->clubId) : null;

        // Only for members of the club (or platform admins).
        $user = auth()->user();

        return $club && $user instanceof User && ($user->isPlatformAdmin() || $user->clubs()->whereKey($club->id)->exists()) ? $club : null;
    }
}
