<?php

namespace App\Planner\Jobs;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\RssPlanningImportService;
use App\Support\Tenancy\ClubJob;
use Illuminate\Support\Facades\Log;

/**
 * The nightly Nevobo sync of one club: fetches the RSS feeds of its teams and
 * updates the newest draft version without overwriting manual planning.
 */
class SyncNevoboPlanningJob extends ClubJob
{
    /** The outcome of the last run, for the command and tests; null when skipped. */
    public ?array $result = null;

    protected function handleForClub(): void
    {
        // Without an existing draft the service would create a new one every
        // night; then the committee no longer decides where the planning lands.
        $version = ScheduleVersion::query()
            ->where('status', ScheduleVersionStatus::Draft)
            ->latest()
            ->latest('id')
            ->first();

        if (!$version) {
            Log::info('planner-sync-nevobo: no draft version, skipped', ['club_id' => $this->club->getKey()]);

            return;
        }

        $this->result = app(RssPlanningImportService::class)->sync($version);

        Log::info('planner-sync-nevobo: synchronised', [
            'club_id' => $this->club->getKey(),
            'added' => $this->result['added'],
            'updated' => $this->result['updated'],
            'applied' => $this->result['applied'],
            'conflicts' => $this->result['conflicts'],
            'skipped' => $this->result['skipped'],
        ]);
        foreach ($this->result['errors'] as $error) {
            Log::warning('planner-sync-nevobo: '.$error, ['club_id' => $this->club->getKey()]);
        }
    }
}
