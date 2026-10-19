<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Fakes the public Nevobo API with the recorded fixtures in
 * tests/Fixtures/Nevobo: two pages of clubs (AETOS, code CNH8Q1U, on page 2;
 * Memesa is dissolved), two pages of AETOS teams and HS 1's pool placements.
 */
trait FakesNevobo
{
    protected function fakeNevobo(): void
    {
        Sleep::fake();
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/relatiebeheer/verenigingen' => Http::response($this->nevoboFixture('clubs-page-'.($query['page'] ?? 1).'.json')),
                $path === '/competitie/teams' && ($query['vereniging'] ?? null) === '/relatiebeheer/verenigingen/cnh8q1u' => Http::response($this->nevoboFixture('teams-page-'.($query['page'] ?? 1).'.json')),
                $path === '/competitie/pouleindelingen' && ($query['team'] ?? null) === '/competitie/teams/cnh8q1u/heren/1' => Http::response($this->nevoboFixture('pouleindelingen-heren-1.json')),
                $path === '/competitie/pouleindelingen' => Http::response(['hydra:member' => []]),
                default => Http::response('', 404),
            };
        });
    }

    protected function nevoboFixture(string $file): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/Nevobo/'.$file));
    }
}
