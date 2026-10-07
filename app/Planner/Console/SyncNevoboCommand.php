<?php

namespace App\Planner\Console;

use App\Models\Club;
use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Jobs\SyncNevoboPlanningJob;
use Illuminate\Console\Command;

/**
 * Queues the Nevobo sync per club. Scheduled daily with --all; each club runs
 * as its own job on its own queue (see ClubJob).
 */
class SyncNevoboCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:sync-nevobo
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}';

    protected $description = 'Haalt de Nevobo-RSS op en werkt de conceptplanning bij zonder handmatige planning te overschrijven';

    public function handle(): int
    {
        return $this->forEachClub(function (Club $club): int {
            if (!$club->is_active) {
                $this->components->warn(__(':club is niet actief, overgeslagen.', ['club' => $club->name]));

                return self::SUCCESS;
            }

            SyncNevoboPlanningJob::dispatch($club);
            $this->components->info(__(':club: synchronisatie in de wachtrij gezet.', ['club' => $club->name]));

            return self::SUCCESS;
        });
    }
}
