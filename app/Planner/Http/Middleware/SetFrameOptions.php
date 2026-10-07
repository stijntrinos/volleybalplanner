<?php

namespace App\Planner\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Framing rules for the public club pages. Normally only the planner itself
 * may frame them; with ?embed=1 a club may embed the page on its own website,
 * so any ancestor is allowed and X-Frame-Options is left out (it would win
 * over the CSP in older browsers).
 */
class SetFrameOptions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->boolean('embed')) {
            $response->headers->remove('X-Frame-Options');
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        } else {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        }

        return $response;
    }
}
