<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml for the public site. Dynamic so they carry the
 * real domain, and so a non-production environment is never indexed.
 */
class SeoController extends Controller
{
    /** The marketing pages; club pages (/{club}) are the clubs' own business. */
    private const PAGES = ['site.home', 'site.features', 'site.pricing', 'site.contact', 'site.privacy', 'site.terms'];

    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /app/', 'Disallow: /admin/', 'Disallow: /uitnodiging/', 'Disallow: /livewire/', '', 'Sitemap: '.route('site.sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $urls = collect(self::PAGES)
            ->map(fn (string $route): string => '  <url><loc>'.e(route($route)).'</loc></url>')
            ->implode("\n");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls."\n"
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
