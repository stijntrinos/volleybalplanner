<?php

namespace App\Console\Commands;

use App\Models\Club;
use Illuminate\Console\Command;

/**
 * Deletes demo clubs some time after their trial ended. Deleting a club
 * purges its planner data and memberships (ClubDataPurger); the users stay.
 * A demo converted into a customer is no longer a demo and is kept.
 */
class PruneDemoClubs extends Command
{
    protected $signature = 'platform:prune-demo-clubs';

    protected $description = 'Verwijder democlubs waarvan de proefperiode al een tijd voorbij is';

    public function handle(): int
    {
        $days = (int) config('planner.demo.prune_after_days', 30);
        $deleted = 0;

        Club::query()->expiredDemo($days)->each(function (Club $club) use (&$deleted): void {
            $club->delete();
            $deleted++;
        });

        $this->info(trans_choice(':count democlub verwijderd.|:count democlubs verwijderd.', $deleted, ['count' => $deleted]));

        return self::SUCCESS;
    }
}
