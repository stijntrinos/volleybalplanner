<?php

namespace App\Planner\Http\Middleware;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubFeature
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($this->currentClub->require()->hasFeature(PlannerFeature::from($feature)), 404);

        return $next($request);
    }
}
