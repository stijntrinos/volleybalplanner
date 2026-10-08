<?php

namespace Tests\Feature\Site;

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
            ->assertSee('<meta property="og:title" content="Prijzen · Volleybalplanner">', false)
            ->assertSee('<meta property="og:image" content="'.asset('images/og-volleybalplanner.png').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('prijzen').'">', false);
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
}
