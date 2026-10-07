<?php

namespace App\Planner\Console\Concerns;

use App\Models\Club;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Collection;

/**
 * For planner commands that work on one club (--club=<slug>) or on every
 * active club (--all). The command signature declares both options:
 *   {--club= : Slug van de club} {--all : Voor alle actieve clubs}
 */
trait RunsPerClub
{
    /**
     * The clubs to work on, or null after reporting an error.
     *
     * @return Collection<int, Club>|null
     */
    protected function clubs(): ?Collection
    {
        $slug = trim((string) $this->option('club'));
        $all = (bool) $this->option('all');

        if (($slug === '') === !$all) {
            $this->components->error(__('Geef --club=<slug> of --all op (niet allebei).'));

            return null;
        }

        if ($all) {
            return Club::query()->where('is_active', true)->orderBy('id')->get();
        }

        $club = Club::query()->where('slug', $slug)->first();
        if (!$club) {
            $this->components->error(__('Club ":slug" bestaat niet.', ['slug' => $slug]));

            return null;
        }

        return collect([$club]);
    }

    /**
     * Runs the callback for every selected club inside CurrentClub::run().
     * Returns FAILURE when the clubs could not be resolved or any club failed.
     *
     * @param  callable(Club): int  $callback
     */
    protected function forEachClub(callable $callback): int
    {
        $clubs = $this->clubs();
        if ($clubs === null) {
            return self::FAILURE;
        }

        $result = self::SUCCESS;
        foreach ($clubs as $club) {
            if ($clubs->count() > 1) {
                $this->components->info($club->name);
            }
            $status = app(CurrentClub::class)->run($club, fn (): int => $callback($club));
            if ($status !== self::SUCCESS) {
                $result = $status;
            }
        }

        return $result;
    }
}
