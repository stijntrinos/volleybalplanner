<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use App\Support\Privacy\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageViewTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile Safari/604.1');
    }

    public function test_statistics_require_explicit_consent(): void
    {
        $this->get('/')->assertOk();
        $this->withCookie(CookieConsent::COOKIE, 'v1:necessary')->get('/')->assertOk();
        $this->withCookie(CookieConsent::COOKIE, 'invalid')->get('/')->assertOk();

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_consented_views_store_only_the_path_host_device_and_daily_session_hash(): void
    {
        $this->freezeTime();

        $this->withCookie(CookieConsent::COOKIE, 'v1:analytics')
            ->withHeader('Referer', 'https://example.org/private?email=secret@example.org')
            ->get('/functies?email=secret@example.org')->assertOk();

        $view = PageView::query()->sole();
        $this->assertSame('/functies', $view->path);
        $this->assertSame('example.org', $view->referrer_host);
        $this->assertSame('mobile', $view->device_type);
        $this->assertSame(64, strlen($view->visit_hash));
        $this->assertNotSame(session()->getId(), $view->visit_hash);
        $this->assertTrue($view->viewed_at->equalTo(now()->startOfSecond()));
        $this->assertStringNotContainsString('secret@example.org', $view->toJson());
    }

    public function test_visits_share_a_hash_within_a_day_and_rotate_on_the_next_day(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        $this->withCookie(CookieConsent::COOKIE, 'v1:analytics')->withSession(['visitor-test' => true]);
        $this->get('/')->assertOk();
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->get('/functies')->assertOk();
        $this->travel(1)->days();
        $this->get('/contact')->assertOk();

        $views = PageView::query()->orderBy('id')->get();
        $this->assertCount(3, $views);
        $this->assertSame($views[0]->visit_hash, $views[1]->visit_hash);
        $this->assertNotSame($views[0]->visit_hash, $views[2]->visit_hash);
    }

    #[DataProvider('excludedRequests')]
    public function test_non_page_requests_and_bots_are_not_counted(string $path, array $headers): void
    {
        $this->withCookie(CookieConsent::COOKIE, 'v1:analytics')->withHeaders($headers)->get($path);

        $this->assertDatabaseCount('page_views', 0);
    }

    public static function excludedRequests(): array
    {
        return [
            'bot' => ['/', ['User-Agent' => 'Googlebot']],
            'ajax' => ['/', ['X-Requested-With' => 'XMLHttpRequest']],
            'prefetch' => ['/', ['Sec-Purpose' => 'prefetch']],
            'robots' => ['/robots.txt', []],
            'sitemap' => ['/sitemap.xml', []],
            'not found' => ['/missing/page', []],
            'login' => ['/app/login', []],
            'invitation' => ['/uitnodiging/secret', []],
        ];
    }

    public function test_platform_admins_are_excluded(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create())
            ->withCookie(CookieConsent::COOKIE, 'v1:analytics')->get('/')->assertOk();

        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_withdrawing_consent_stops_subsequent_measurements(): void
    {
        $this->withCookie(CookieConsent::COOKIE, 'v1:analytics')->get('/')->assertOk();
        $this->post('/cookies', ['analytics' => false])->assertCookie(CookieConsent::COOKIE, 'v1:necessary');
        $this->withCookie(CookieConsent::COOKIE, 'v1:necessary')->get('/functies')->assertOk();

        $this->assertDatabaseCount('page_views', 1);
    }

    public function test_old_statistics_are_pruned(): void
    {
        $this->freezeTime();
        $old = PageView::factory()->create(['viewed_at' => now()->subYear()->subDay()]);
        $recent = PageView::factory()->create();

        $this->artisan('model:prune', ['--model' => [PageView::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
