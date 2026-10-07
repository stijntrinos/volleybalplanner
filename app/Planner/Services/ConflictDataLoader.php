<?php

namespace App\Planner\Services;

use App\Models\Club;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Activity;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The data the conflict engine checks against, loaded once per club or
 * version and kept for the lifetime of the engine. A version scan asks for it
 * per match; without these caches that was hundreds of queries.
 */
class ConflictDataLoader
{
    /** @var array<int, Collection<int, Training>> */
    private array $trainingsCache = [];

    /** @var array<int, Collection<int, Activity>> */
    private array $activitiesCache = [];

    /** @var array<int, PlannerSetting> */
    private array $settingsCache = [];

    /** @var array<int, Collection<int, Team>> */
    private array $teamsCache = [];

    /**
     * Trainings are stored per weekday, not per date, and only apply within
     * the configured training period, as in the day view. Without the
     * trainings module there are none.
     *
     * @return Collection<int, Training>
     */
    public function trainingsOn(int $clubId, string $date): Collection
    {
        if (!$this->club()->hasFeature(PlannerFeature::Trainings)) {
            return collect();
        }

        $settings = $this->settings($clubId);
        $day = CarbonImmutable::parse($date);
        $withinPeriod = (!$settings->training_period_start || $day->greaterThanOrEqualTo($settings->training_period_start))
            && (!$settings->training_period_end || $day->lessThanOrEqualTo($settings->training_period_end));
        if (!$withinPeriod) {
            return collect();
        }

        $this->trainingsCache[$clubId] ??= Training::query()->with('team')->get();

        return $this->trainingsCache[$clubId]->where('weekday', Weekday::name($day));
    }

    /** @return Collection<int, Activity> */
    public function activitiesOn(?int $versionId, string $date): Collection
    {
        if (!$versionId) {
            return collect();
        }

        $this->activitiesCache[$versionId] ??= Activity::query()->where('version_id', $versionId)->get();

        return $this->activitiesCache[$versionId]
            ->filter(fn (Activity $activity): bool => $activity->date->toDateString() === $date);
    }

    /**
     * The away match list asks for conflicts per row; without this cache the
     * team list went to the database again for every row.
     *
     * @return Collection<int, Team>
     */
    public function teams(int $clubId): Collection
    {
        return $this->teamsCache[$clubId] ??= Team::query()->get();
    }

    public function settings(int $clubId): PlannerSetting
    {
        return $this->settingsCache[$clubId] ??= PlannerSetting::current();
    }

    private function club(): Club
    {
        return app(CurrentClub::class)->require();
    }
}
