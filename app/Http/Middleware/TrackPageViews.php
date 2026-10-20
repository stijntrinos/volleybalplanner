<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\Privacy\CookieConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TrackPageViews
{
    public function __construct(private CookieConsent $consent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200
            || !str_contains($response->headers->get('Content-Type', ''), 'text/html')
            || $request->ajax() || $request->pjax() || $request->headers->has('Purpose')
            || $request->headers->has('Sec-Purpose') || $request->user()?->isPlatformAdmin()
            || !$this->consent->analyticsAllowed($request)) {
            return $response;
        }

        $agent = mb_strtolower($request->userAgent() ?? '');
        if ($agent === '' || preg_match('/bot|crawl|spider|slurp|scraper|scanner|monitor|headless|preview/', $agent)) {
            return $response;
        }

        try {
            $referrer = parse_url($request->header('referer', ''), PHP_URL_HOST);
            $device = match (true) {
                str_contains($agent, 'ipad'), str_contains($agent, 'tablet'), str_contains($agent, 'android') && !str_contains($agent, 'mobile') => 'tablet',
                str_contains($agent, 'mobile'), str_contains($agent, 'iphone'), str_contains($agent, 'android') => 'mobile',
                default => 'desktop',
            };

            PageView::query()->create([
                'path' => '/'.ltrim($request->path(), '/'),
                'visit_hash' => hash_hmac('sha256', now()->toDateString().'|'.$request->session()->getId(), config('app.key')),
                'referrer_host' => is_string($referrer) && mb_strtolower($referrer) !== mb_strtolower($request->getHost()) ? mb_substr(mb_strtolower($referrer), 0, 255) : null,
                'device_type' => $device,
                'viewed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $response;
    }
}
