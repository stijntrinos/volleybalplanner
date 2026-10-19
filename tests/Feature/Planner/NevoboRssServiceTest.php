<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Services\NevoboRssService;
use App\Planner\Support\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class NevoboRssServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_nevobo_feed_is_parsed_and_cached(): void
    {
        $team = Team::factory()->create();
        $source = TeamSource::factory()->create(['team_id' => $team->id, 'name' => 'Nevobo', 'url' => 'https://example.test/feed.xml']);
        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel><item><title>AETOS HS1 - Dynamo HS1 (ABC-1 A)</title><description>Datum 12-09-2026 10:30 | Locatie: De Laar</description></item></channel></rss>')]);

        $first = app(NevoboRssService::class)->fetch($source);
        $second = app(NevoboRssService::class)->fetch($source);

        $this->assertTrue($first['ok']);
        $this->assertSame('2026-09-12', $first['items'][0]['date']);
        $this->assertSame('10:30', $first['items'][0]['time']);
        $this->assertSame('De Laar', $first['items'][0]['location']);
        $this->assertFalse($first['cached']);
        $this->assertTrue($second['cached']);
        $this->assertNotNull($source->refresh()->last_succeeded_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->header('User-Agent')[0] === config('app.name').'/1.0');
    }

    public function test_a_failing_feed_records_the_error(): void
    {
        $source = TeamSource::factory()->create(['url' => 'https://example.test/broken.xml']);
        Http::fake(['example.test/*' => Http::response('', 500)]);

        $result = app(NevoboRssService::class)->fetch($source);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['reason']);
        $this->assertNotNull($source->refresh()->last_error);
    }

    /** @return array<string, array{string}> */
    public static function internalUrls(): array
    {
        return [
            'loopback ip' => ['https://127.0.0.1/feed.xml'],
            'short loopback' => ['https://127.1/feed.xml'],
            'private range' => ['https://10.0.0.5/feed.xml'],
            'metadata service' => ['https://169.254.169.254/latest/meta-data'],
            'ipv6 loopback' => ['https://[::1]/feed.xml'],
            'trailing dot' => ['https://localhost./feed.xml'],
        ];
    }

    #[DataProvider('internalUrls')]
    public function test_internal_addresses_are_refused(string $url): void
    {
        $source = TeamSource::factory()->create(['url' => $url]);
        Http::fake();

        try {
            app(NevoboRssService::class)->fetch($source);
            $this->fail('An internal feed URL was fetched.');
        } catch (InvalidArgumentException) {
            Http::assertNothingSent();
        }
    }

    public function test_a_host_name_pointing_at_an_internal_address_is_refused(): void
    {
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function ipv4(string $host): array
            {
                return ['10.0.0.7'];
            }
        });
        $source = TeamSource::factory()->create(['url' => 'https://feeds.attacker.example/x.xml']);
        Http::fake();

        $this->expectException(InvalidArgumentException::class);
        app(NevoboRssService::class)->fetch($source);
    }

    public function test_redirects_are_not_followed(): void
    {
        $source = TeamSource::factory()->create(['url' => 'https://example.test/feed.xml']);
        Http::fake(['example.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

        $result = app(NevoboRssService::class)->fetch($source);

        $this->assertFalse($result['ok']);
        Http::assertSentCount(1);
    }

    public function test_the_stored_error_does_not_contain_the_response(): void
    {
        $source = TeamSource::factory()->create(['url' => 'https://example.test/broken.xml']);
        Http::fake(['example.test/*' => Http::response('secret internal page', 500)]);

        app(NevoboRssService::class)->fetch($source);

        $this->assertStringNotContainsString('secret', (string) $source->refresh()->last_error);
    }

    public function test_only_public_https_urls_are_allowed(): void
    {
        $source = TeamSource::factory()->create(['url' => 'http://localhost/feed.xml']);

        $this->expectException(InvalidArgumentException::class);
        app(NevoboRssService::class)->fetch($source);
    }

    public function test_nevobo_title_with_date_prefix_gives_clean_team_names(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title>5 sep 15:00: AETOS Plauti DS 1 - Nuovo DS 1</title>
                <pubDate>Sat, 05 Sep 2026 15:00:00 +0200</pubDate>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/test.rss');

        $this->assertSame('AETOS Plauti DS 1', $items[0]['home_team']);
        $this->assertSame('Nuovo DS 1', $items[0]['away_team']);
        $this->assertSame('2026-09-05', $items[0]['date']);
        $this->assertSame('15:00', $items[0]['time']);
        $this->assertFalse($items[0]['cancelled']);
    }

    public function test_current_nevobo_feed_gives_match_code_and_clean_venue(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title><![CDATA[26 sep 15:00: AETOS Plauti DS 1 - Trivos DS 3]]></title>
                <link>https://api.nevobo.nl/permalink/wedstrijd/1987059</link>
                <guid>https://api.nevobo.nl/permalink/wedstrijd/1987059</guid>
                <description><![CDATA[Wedstrijd: 50003CD   DG, Datum: zaterdag 26 september, 15:00, Speellocatie: Sporthal Middachtensingel, Middachtensingel 4, 6825HN Arnhem]]></description>
                <pubDate>Sat, 26 Sep 2026 15:00:00 +0200</pubDate>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/export/poule/regio-oost/3CD/programma.rss');

        // The feed pads the pool code to six characters; the planning file and
        // the federation documents write the same match as "3CD-DG".
        $this->assertSame('3CD-DG', $items[0]['code']);
        $this->assertSame('Sporthal Middachtensingel', $items[0]['location']);
        $this->assertSame('AETOS Plauti DS 1', $items[0]['home_team']);
        $this->assertSame('Trivos DS 3', $items[0]['away_team']);
        $this->assertSame('https://api.nevobo.nl/export/poule/regio-oost/3CD/programma.rss', $items[0]['source_url']);
    }

    public function test_a_pool_code_without_padding_gives_the_same_notation(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title><![CDATA[12 sep 14:00: Renswouw DS 1 - Nuovo DS 1]]></title>
                <description><![CDATA[Wedstrijd: 5000BD1A03BD, Datum: zaterdag 12 september, 14:00, Speellocatie: Hokhorst, De Hokhorst 1, 3927GX Renswoude]]></description>
                <pubDate>Sat, 12 Sep 2026 14:00:00 +0200</pubDate>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/export/poule/regio-oost/BD1A03/programma.rss');

        // Six pool characters leave no space. This form went wrong: it stayed
        // "5000BD1A03BD" and never matched the planning.
        $this->assertSame('BD1A03-BD', $items[0]['code']);
    }

    public function test_a_club_name_with_a_dash_is_not_split_on_that_dash(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title>WRA Juristen - Rebelle HS 2 - AETOS HS 3</title>
                <description>Datum 03-10-2026 16:00 | Locatie: Rijkerswoerd</description>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/test.rss');

        $this->assertSame('WRA Juristen - Rebelle HS 2', $items[0]['home_team']);
        $this->assertSame('AETOS HS 3', $items[0]['away_team']);
    }

    public function test_a_title_without_recognisable_team_code_splits_on_the_first_dash(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title>Team Alfa - Team Bravo</title>
                <description>Datum 03-10-2026 16:00</description>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/test.rss');

        $this->assertSame('Team Alfa', $items[0]['home_team']);
        $this->assertSame('Team Bravo', $items[0]['away_team']);
    }

    public function test_a_cancelled_match_gives_clean_team_names(): void
    {
        $items = app(NevoboRssService::class)->parse(<<<'XML'
            <rss><channel><item>
                <title><![CDATA[Vervallen: 17 okt 9:00: AETOS MC 5 - TweeVV XC 2]]></title>
                <description><![CDATA[Wedstrijd: 5000XC3K1 AD, Datum: zaterdag 17 oktober, 9:00, Speellocatie: Middachtensingel]]></description>
                <pubDate>Sat, 17 Oct 2026 09:00:00 +0200</pubDate>
            </item></channel></rss>
            XML, 'https://api.nevobo.nl/export/poule/regio-oost/XC3K1/programma.rss');

        // Without removing "Vervallen: " the whole title stayed as the home
        // team, including date and time.
        $this->assertSame('AETOS MC 5', $items[0]['home_team']);
        $this->assertSame('TweeVV XC 2', $items[0]['away_team']);
        $this->assertSame('XC3K1-AD', $items[0]['code']);
        $this->assertTrue($items[0]['cancelled']);
    }

    /**
     * A team feed (/export/team/{club}/{type}/{n}/programma.rss), as set up by
     * the Nevobo link, has no numeric date in the description: the date
     * comes from pubDate and the match code from "Wedstrijd:".
     */
    public function test_a_team_programme_feed_is_parsed(): void
    {
        $xml = (string) file_get_contents(base_path('tests/Fixtures/Nevobo/team-programma.rss'));
        $url = 'https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/programma.rss';

        $items = app(NevoboRssService::class)->parse($xml, $url);

        $this->assertCount(3, $items);
        $this->assertSame([
            'code' => '3CH-IE', 'date' => '2026-11-21', 'time' => '17:00',
            'home_team' => 'AETOS HS 1', 'away_team' => 'Scylla HS 2',
            'location' => 'Sporthal Middachtensingel', 'pool' => null,
            'source_url' => $url, 'cancelled' => false,
        ], $items[2]);
        $this->assertSame(['Volga HS 1', 'AETOS HS 1', '2026-10-31'], [$items[0]['home_team'], $items[0]['away_team'], $items[0]['date']]);
    }
}
