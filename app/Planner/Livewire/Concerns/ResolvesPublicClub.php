<?php

namespace App\Planner\Livewire\Concerns;

use App\Models\Club;
use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Livewire\Attributes\Locked;

/**
 * Public components live on /{club}/...; the club middleware sets the current
 * club for the first render only. Livewire's follow-up requests go to its own
 * endpoint, so the component remembers the club and restores it (with the
 * same active and module checks) on every request.
 */
trait ResolvesPublicClub
{
    #[Locked]
    public ?int $clubId = null;

    /** @return list<PlannerFeature> modules the component needs */
    protected function requiredFeatures(): array
    {
        return [];
    }

    public function mountResolvesPublicClub(): void
    {
        $club = app(CurrentClub::class)->get();
        $this->guardClub($club);
        $this->clubId = $club->getKey();
    }

    public function hydrateResolvesPublicClub(): void
    {
        $club = $this->clubId !== null ? Club::query()->find($this->clubId) : null;
        $this->guardClub($club);
        app(CurrentClub::class)->set($club);
    }

    /** @phpstan-assert Club $club */
    private function guardClub(?Club $club): void
    {
        abort_if(!$club instanceof Club || !$club->is_active, 404);

        foreach ($this->requiredFeatures() as $feature) {
            abort_unless($club->hasFeature($feature), 404);
        }
    }
}
