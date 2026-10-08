<?php

namespace App\Planner\Filament\Widgets\Concerns;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;

/**
 * The dashboard reports on the planning that is live: the published version,
 * or the newest draft while nothing is published yet.
 */
trait ResolvesDashboardVersion
{
    private ?ScheduleVersion $publishedVersion = null;

    private ?ScheduleVersion $draftVersion = null;

    protected function publishedVersion(): ?ScheduleVersion
    {
        return $this->publishedVersion ??= ScheduleVersion::query()
            ->where('status', ScheduleVersionStatus::Published)
            ->latest('published_at')
            ->first();
    }

    protected function draftVersion(): ?ScheduleVersion
    {
        return $this->draftVersion ??= ScheduleVersion::query()
            ->where('status', ScheduleVersionStatus::Draft)
            ->latest()
            ->latest('id')
            ->first();
    }

    protected function liveVersion(): ?ScheduleVersion
    {
        return $this->publishedVersion() ?? $this->draftVersion();
    }
}
