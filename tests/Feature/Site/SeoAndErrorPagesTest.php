<?php

namespace Tests\Feature\Site;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SeoAndErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_robots_keep_the_panels_out_and_point_to_the_sitemap_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/robots.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /app/')
            ->assertSee('Disallow: /admin/')
            ->assertSee('Sitemap: '.url('sitemap.xml'));
    }

    public function test_other_environments_are_not_indexed_at_all(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    }

    public function test_the_sitemap_lists_the_public_pages(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('<loc>'.url('/').'</loc>', false)
            ->assertSee('<loc>'.url('prijzen').'</loc>', false)
            ->assertSee('<loc>'.url('voorwaarden').'</loc>', false);
    }

    public function test_pages_carry_open_graph_tags_and_a_canonical_url(): void
    {
        $this->get('/prijzen')->assertOk()
            ->assertSee('<meta property="og:title" content="Prijzen per seizoen · Volleybalplanner">', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og-volleybalplanner.png').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('prijzen').'">', false);
    }

    public function test_the_pricing_page_describes_its_offers_and_faq_as_structured_data(): void
    {
        Plan::factory()->create(['name' => 'Basis', 'price' => 349, 'is_public' => true]);
        Plan::factory()->create(['name' => 'Op maat', 'price' => null, 'is_public' => true]);

        $graph = $this->structuredData($this->get('/prijzen')->assertOk()->getContent());
        $types = array_column($graph, '@type');
        $software = $graph[array_search('SoftwareApplication', $types, true)];
        $faq = $graph[array_search('FAQPage', $types, true)];

        $this->assertContains('Organization', $types);
        $this->assertSame([['Basis', 349, 'EUR']], array_map(fn (array $offer): array => [$offer['name'], $offer['price'], $offer['priceCurrency']], $software['offers']));
        $this->assertSame('Kunnen we het eerst uitproberen?', $faq['mainEntity'][0]['name']);
    }

    public function test_a_noindex_page_has_no_canonical_or_structured_data(): void
    {
        $this->get('/uitnodiging/onbekend')->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);
    }

    public function test_the_panels_ask_not_to_be_indexed(): void
    {
        $this->get('/app/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/admin/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_an_unknown_page_shows_the_own_not_found_page(): void
    {
        $this->get('/deze-club-bestaat-niet')->assertNotFound()
            ->assertSee('Pagina niet gevonden')
            ->assertSee('Naar de homepage');
    }

    public function test_server_errors_and_maintenance_have_their_own_page_too(): void
    {
        $this->assertStringContainsString('Er ging iets mis', view('errors.500')->render());
        $this->assertStringContainsString('Even onderhoud', view('errors.503')->render());
        $this->assertStringContainsString('502', view('errors.5xx', ['exception' => new HttpException(502)])->render());
    }

    /** @return list<array<string, mixed>> */
    private function structuredData(string $html): array
    {
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['@graph'];
    }
}
