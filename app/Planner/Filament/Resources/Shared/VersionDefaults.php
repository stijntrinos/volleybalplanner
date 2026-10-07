<?php

namespace App\Planner\Filament\Resources\Shared;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\UserPreference;
use Illuminate\Database\Eloquent\Builder;

/**
 * Version helpers shared by the resources whose records belong to a schedule
 * version (activities, hall duties, VolleyStars blocks).
 */
final class VersionDefaults
{
    /** New records go into the newest draft. */
    public static function latestDraftId(): ?int
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->latest('id')->value('id');
    }

    /** Only drafts can receive new records. */
    public static function onlyDrafts(Builder $query): Builder
    {
        return $query->where('status', ScheduleVersionStatus::Draft);
    }

    /**
     * The list filter starts at the version the user works in: their active
     * version, otherwise the newest draft, otherwise the published one.
     */
    public static function filterDefault(): ?int
    {
        return UserPreference::query()->where('user_id', auth()->id())->value('active_version_id')
            ?? self::latestDraftId()
            ?? ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->latest('id')->value('id');
    }

    public static function statusColor(?ScheduleVersionStatus $status): string
    {
        return match ($status) {
            ScheduleVersionStatus::Published => 'success',
            ScheduleVersionStatus::Draft => 'warning',
            default => 'gray',
        };
    }

    public static function statusDescription(?ScheduleVersionStatus $status): string
    {
        return match ($status) {
            ScheduleVersionStatus::Published => __('Actieve planning'),
            ScheduleVersionStatus::Draft => __('Concept'),
            ScheduleVersionStatus::Archived => __('Gearchiveerd'),
            default => __('Onbekende status'),
        };
    }
}
