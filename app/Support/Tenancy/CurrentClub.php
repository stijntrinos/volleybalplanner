<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Filament\Facades\Filament;

/**
 * The single source of "which club is active". Panel requests get it from the
 * Filament tenant, public routes from ResolveClubFromRoute, jobs and commands
 * from run().
 */
class CurrentClub
{
    private ?Club $club = null;

    public function get(): ?Club
    {
        if ($this->club !== null) {
            return $this->club;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Club ? $tenant : null;
    }

    public function require(): Club
    {
        return $this->get() ?? throw new NoCurrentClub;
    }

    public function id(): int
    {
        return $this->require()->getKey();
    }

    public function set(?Club $club): void
    {
        $this->club = $club;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Club $club, callable $callback): mixed
    {
        $previous = $this->club;
        $this->club = $club;

        try {
            return $callback();
        } finally {
            $this->club = $previous;
        }
    }
}
