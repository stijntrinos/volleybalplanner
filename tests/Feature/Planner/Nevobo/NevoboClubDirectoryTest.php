<?php

namespace Tests\Feature\Planner\Nevobo;

use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Services\Nevobo\NevoboClubDirectory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Tests\Concerns\FakesNevobo;
use Tests\TestCase;

class NevoboClubDirectoryTest extends TestCase
{
    use FakesNevobo;

    public function test_search_reads_every_page_once_and_matches_name_official_name_and_city(): void
    {
        $this->fakeNevobo();
        $directory = app(NevoboClubDirectory::class);

        $byName = $directory->search('aetos');
        $byOfficialName = $directory->search('VOLAR');
        $byCity = $directory->search('arnhem');

        $this->assertSame([[
            'code' => 'CNH8Q1U', 'name' => 'AETOS', 'official_name' => 'AVF VOLAR', 'city' => 'Arnhem', 'region' => 'Oost',
        ]], $byName);
        $this->assertSame('CNH8Q1U', $byOfficialName[0]['code']);
        $this->assertSame('CNH8Q1U', $byCity[0]['code']);
        Http::assertSentCount(2);
    }

    public function test_dissolved_clubs_and_too_short_queries_find_nothing(): void
    {
        $this->fakeNevobo();
        $directory = app(NevoboClubDirectory::class);

        $this->assertSame([], $directory->search('Memesa'));
        $this->assertSame([], $directory->search('a'));
        $this->assertSame('LSV', $directory->club('ckl5c67')['name']);
    }

    public function test_teams_of_all_pages_get_planner_codes(): void
    {
        $this->fakeNevobo();

        $teams = collect(app(NevoboClubDirectory::class)->teams('cnh8q1u'))->keyBy('nevobo_id');

        $this->assertSame(
            ['HS 1', 'DS 1', 'DS 11', 'MA 1', 'JC 1', 'N6 1', 'XZ 1', 'L5 1'],
            $teams->pluck('code')->values()->all(),
        );
        $hs1 = $teams['/competitie/teams/cnh8q1u/heren/1'];
        $this->assertSame('men', $hs1['category']);
        $this->assertSame('1e in Heren Derde divisie C', $hs1['standing_text']);
        $this->assertSame('https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/programma.rss', $hs1['schedule_url']);
        $this->assertSame('https://api.nevobo.nl/export/team/CNH8Q1U/heren/1/resultaten.rss', $hs1['results_url']);
        $this->assertSame('AETOS Plauti DS 1', $teams['/competitie/teams/cnh8q1u/dames/1']['name']);
        $this->assertSame('other', $teams['/competitie/teams/cnh8q1u/mix-zitvolleybal/1']['category']);
        $this->assertSame(
            ['HS 1', 'DS 1', 'DS 11', 'MA 1', 'JC 1', 'XZ 1'],
            $teams->where('regular', true)->pluck('code')->values()->all(),
        );
        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->url()), 'vereniging=/relatiebeheer/verenigingen/cnh8q1u&page=2'));
    }

    public function test_standings_come_from_the_competition_pool_not_the_cup(): void
    {
        $this->fakeNevobo();

        $urls = app(NevoboClubDirectory::class)->standingsUrls(['/competitie/teams/cnh8q1u/heren/1', '/competitie/teams/cnh8q1u/dames/1']);

        $this->assertSame([
            '/competitie/teams/cnh8q1u/heren/1' => 'https://api.nevobo.nl/export/poule/regio-oost/competitie-seniorencompetitie-2/regio-oost-3ch-2/stand.rss',
        ], $urls);
    }

    public function test_a_server_error_gives_a_readable_reason_is_logged_and_not_cached(): void
    {
        Sleep::fake();
        Log::spy();
        Http::fake(['api.nevobo.nl/*' => Http::sequence()->push('', 503)->push('', 503)->push('', 503)
            ->push($this->singlePageOfClubs())]);
        $directory = app(NevoboClubDirectory::class);

        try {
            $directory->search('aetos');
            $this->fail('Expected NevoboUnavailable');
        } catch (NevoboUnavailable $exception) {
            $this->assertStringContainsString('tijdelijk niet beschikbaar (HTTP 503)', $exception->getMessage());
        }

        Log::shouldHaveReceived('warning')->once();
        $this->assertSame('CNH8Q1U', $directory->search('aetos')[0]['code']);
    }

    public function test_rate_limiting_is_retried_before_giving_up(): void
    {
        Sleep::fake();
        Http::fake(['api.nevobo.nl/*' => Http::sequence()->push('', 429)->push($this->singlePageOfClubs())]);

        $this->assertSame('CNH8Q1U', app(NevoboClubDirectory::class)->search('aetos')[0]['code']);
        Http::assertSentCount(2);
    }

    public function test_a_timeout_gives_a_readable_reason(): void
    {
        Sleep::fake();
        Http::fake(['api.nevobo.nl/*' => Http::failedConnection()]);

        $this->expectException(NevoboUnavailable::class);
        $this->expectExceptionMessage('De Nevobo is niet bereikbaar');

        app(NevoboClubDirectory::class)->teams('CNH8Q1U');
    }

    /** @return array<string, mixed> page 2 of the fixture as the only page */
    private function singlePageOfClubs(): array
    {
        $page = json_decode($this->nevoboFixture('clubs-page-2.json'), true);
        unset($page['hydra:view']);

        return $page;
    }
}
