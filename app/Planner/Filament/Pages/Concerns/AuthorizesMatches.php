<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use DomainException;

/**
 * Permission and scoping guards for every write path on the planner pages.
 *
 * The page permission only answers "may this user open the page". Creating,
 * moving and deleting matches are separate rights, and every lookup stays
 * inside the version the page is showing ($this->versionId).
 */
trait AuthorizesMatches
{
    protected function requireMatchPermission(string $action): void
    {
        abort_unless(auth()->user()?->can($action.':FixtureMatch'), 403);
    }

    /**
     * Matches are always resolved within the selected version, so a crafted
     * Livewire call cannot reach a match from another (possibly published) one.
     */
    protected function matchInVersion(int $matchId): FixtureMatch
    {
        return FixtureMatch::query()->where('version_id', $this->versionId)->findOrFail($matchId);
    }

    /**
     * A published schedule is what the club sees at that moment; it is changed
     * through a new draft version, not directly.
     *
     * @throws DomainException when the selected version is no longer editable
     */
    protected function editableVersion(): ScheduleVersion
    {
        $version = ScheduleVersion::query()->findOrFail($this->versionId);
        if (!$version->isDraft()) {
            throw new DomainException(__('Alleen een conceptplanning kan worden gewijzigd.'));
        }

        return $version;
    }
}
