<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveClubFromRoute
{
    public function __construct(private readonly CurrentClub $currentClub) {}

    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route('club');

        // Route-model binding may already have turned the slug into a Club.
        $club = match (true) {
            $parameter instanceof Club => $parameter,
            is_string($parameter) => Club::query()->where('slug', $parameter)->first(),
            default => null,
        };

        abort_if($club === null || !$club->is_active, 404);

        $this->currentClub->set($club);
        $request->route()->setParameter('club', $club);

        return $next($request);
    }
}
