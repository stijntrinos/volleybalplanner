<?php

namespace App\Planner\Filament\Widgets\Concerns;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\UserPreference;

/**
 * Mirrors how the home matches page picks its version, so the dashboard
 * widgets report on the same planning the user works in.
 */
trait ResolvesWidgetVersion
{
    protected function versionId(): ?int
    {
        $preference = UserPreference::query()->where('user_id', auth()->id())->value('active_version_id');

        return ScheduleVersion::query()->whereKey($preference)->value('id')
            ?? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->value('id')
            ?? ScheduleVersion::query()->latest()->value('id');
    }
}
