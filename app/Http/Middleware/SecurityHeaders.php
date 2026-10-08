<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline headers for every response. Framing defaults to this site only;
 * a route that sets its own policy (the embeddable club pages) keeps it.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
