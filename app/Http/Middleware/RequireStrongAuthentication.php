<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament's "MFA required" for everyone, made selective: only users who
 * must have a passkey or two-factor authentication and don't yet are sent to
 * the set-up page (platform admins, members of a club that requires it).
 */
class RequireStrongAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        // "Mijn account" stays open, so a passkey can be added there instead.
        $onAccountPage = str_ends_with((string) $request->route()?->getName(), '.account');

        if ($user instanceof User && !$onAccountPage && $user->mustSetUpStrongAuthentication()) {
            return redirect()->guest(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
