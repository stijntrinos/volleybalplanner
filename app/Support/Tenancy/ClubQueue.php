<?php

namespace App\Support\Tenancy;

use App\Models\Club;

final class ClubQueue
{
    public static function for(Club $club): string
    {
        return config('planner.queue.mode') === 'per_club'
            ? 'club-'.$club->slug
            : (string) config('planner.queue.shared', 'planner');
    }
}
