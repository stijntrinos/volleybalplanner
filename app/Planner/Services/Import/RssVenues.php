<?php

namespace App\Planner\Services\Import;

use App\Planner\Models\Court;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Support\VenueName;

/**
 * Venue and court lookups for the RSS sync, cached for one run: forty team
 * feeds otherwise mean thousands of queries for data that does not change
 * during the run.
 */
class RssVenues
{
    /** @var array<string, ?Venue> */
    private array $venueCache = [];

    /** @var array<string, ?Court> */
    private array $courtCache = [];

    public function reset(): void
    {
        $this->venueCache = [];
        $this->courtCache = [];
    }

    public function venue(?string $location): ?Venue
    {
        $key = VenueName::key($location);
        if (!array_key_exists($key, $this->venueCache)) {
            $this->venueCache[$key] = $this->find($location);
        }

        return $this->venueCache[$key];
    }

    /** The team's default court (by position among the match courts), or the first one. */
    public function court(Venue $venue, ?Team $team): ?Court
    {
        $position = max(1, $team?->default_court ?: 1);
        $key = $venue->id.'|'.$position;
        if (!array_key_exists($key, $this->courtCache)) {
            $this->courtCache[$key] = $venue->courts()->getQuery()
                ->reorder()
                ->where('is_training_court', false)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->skip($position - 1)
                ->first();
        }

        return $this->courtCache[$key];
    }

    /**
     * The federation writes venues differently than we store them. Comparing
     * raw text produced the same false change notice every night.
     */
    public function isSameVenue(?string $original, ?string $location, ?Venue $venue): bool
    {
        if (VenueName::equals($original, $location)) {
            return true;
        }

        // Both names may point to the same venue through an alias.
        return $venue !== null && $venue->is($this->venue($original));
    }

    /**
     * Unknown locations are deliberately not created. The feeds also contain
     * away matches, so every opponent's location would become a venue and a
     * nightly job would fill the table with venues we never play in at home.
     * Such a venue would not be for matches and so classify exactly like no
     * venue at all. When a team moves to a new venue, an administrator adds
     * it once and the next sync recognises it by name, code or alias.
     */
    private function find(?string $location): ?Venue
    {
        if (!$location) {
            return null;
        }

        return Venue::query()
            ->where(fn ($query) => $query->where('name', $location)->orWhere('venue_code', $location)
                ->orWhereHas('aliases', fn ($aliases) => $aliases->where('name', $location)))
            ->first();
    }
}
