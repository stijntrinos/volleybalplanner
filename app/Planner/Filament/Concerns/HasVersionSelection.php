<?php

namespace App\Planner\Filament\Concerns;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Support\Collection;

/**
 * Every planner page starts from a schedule version. Two deliberate flavours:
 * planning screens start at the draft, viewing/export screens at the published
 * schedule. The select list shows the status so it is clear which one you're in.
 */
trait HasVersionSelection
{
    /** @return Collection<int, string> */
    public function getVersionsProperty(): Collection
    {
        return ScheduleVersion::query()->latest()->get()->mapWithKeys(fn (ScheduleVersion $version): array => [
            $version->id => $version->name.' · '.$version->status->getLabel(),
        ]);
    }

    /** Planning screens: the newest draft, otherwise whatever exists. */
    protected function editableVersionId(): ?int
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->latest('id')->value('id')
            ?? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->value('id')
            ?? ScheduleVersion::query()->latest()->value('id');
    }

    /** Viewing and export screens: the published schedule. */
    protected function viewableVersionId(): ?int
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->value('id')
            ?? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->latest('id')->value('id')
            ?? ScheduleVersion::query()->latest()->value('id');
    }
}
