<?php

namespace App\Planner\Services\Nevobo;

use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Support\TeamCode;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The clubs and teams of the public Nevobo API (JSON-LD). The API has no name
 * filter, so the whole club list (about 60 pages) is fetched in parallel and
 * cached for a day; a club's teams are cached briefly so picking teams and
 * linking them does not ask the federation twice.
 *
 * @phpstan-type NevoboClub array{code: string, name: string, official_name: string, city: string, region: string}
 * @phpstan-type NevoboTeam array{nevobo_id: string, name: string, code: string, category: string, standing_text: ?string, regular: bool, schedule_url: string, results_url: string}
 */
class NevoboClubDirectory
{
    public const BASE_URL = 'https://api.nevobo.nl';

    private const CLUBS_CACHE_KEY = 'planner-nevobo-clubs';

    private const TEAMS_CACHE_PREFIX = 'planner-nevobo-teams:';

    /** Parallel requests per batch; the federation API is shared by every club. */
    private const CONCURRENCY = 10;

    /** A safety net against a pagination loop. */
    private const MAX_PAGES = 200;

    private const SEARCH_LIMIT = 50;

    /**
     * Clubs whose name, official name or city contains the query, best match first.
     *
     * @return list<NevoboClub>
     *
     * @throws NevoboUnavailable
     */
    public function search(string $query): array
    {
        $needle = mb_strtolower(trim($query));

        if (mb_strlen($needle) < 2) {
            return [];
        }

        $matches = array_values(array_filter(
            $this->clubs(),
            static fn (array $club): bool => str_contains(mb_strtolower($club['name']), $needle)
                || str_contains(mb_strtolower($club['official_name']), $needle)
                || str_contains(mb_strtolower($club['city']), $needle),
        ));

        $rank = static function (array $club) use ($needle): int {
            $name = mb_strtolower($club['name']);

            return match (true) {
                $name === $needle => 0,
                str_starts_with($name, $needle) => 1,
                str_contains($name, $needle) => 2,
                default => 3,
            };
        };
        usort($matches, static fn (array $a, array $b): int => [$rank($a), $a['name']] <=> [$rank($b), $b['name']]);

        return array_slice($matches, 0, self::SEARCH_LIMIT);
    }

    /**
     * @return NevoboClub|null
     *
     * @throws NevoboUnavailable
     */
    public function club(string $code): ?array
    {
        $code = Str::upper(trim($code));

        foreach ($this->clubs() as $club) {
            if ($club['code'] === $code) {
                return $club;
            }
        }

        return null;
    }

    /**
     * Every club that still exists, cached for a day. A failure is not cached.
     *
     * @return list<NevoboClub>
     *
     * @throws NevoboUnavailable
     */
    public function clubs(): array
    {
        return Cache::remember(self::CLUBS_CACHE_KEY, now()->addDay(), fn (): array => $this->fetchClubs());
    }

    /**
     * The teams of a club in the current season, in Nevobo order.
     *
     * @return list<NevoboTeam>
     *
     * @throws NevoboUnavailable
     */
    public function teams(string $clubCode, bool $fresh = false): array
    {
        $code = self::normalizeClubCode($clubCode);
        $key = self::TEAMS_CACHE_PREFIX.$code;

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, now()->addMinutes(10), fn (): array => $this->fetchTeams($code));
    }

    /**
     * Per team the standings feed of its current competition pool (not the
     * cup). Teams without one, or whose lookup failed, are left out: the
     * standings are a nice-to-have and must never block linking.
     *
     * @param  list<string>  $nevoboTeamIds
     * @return array<string, string> nevobo team id => stand.rss URL
     */
    public function standingsUrls(array $nevoboTeamIds): array
    {
        $urls = [];

        foreach (array_chunk(array_values(array_unique($nevoboTeamIds)), self::CONCURRENCY) as $batch) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (string $teamId): mixed => $this->configure($pool->as($teamId))
                    ->get(self::BASE_URL.'/competitie/pouleindelingen', ['team' => $teamId]),
                $batch,
            ));

            foreach ($batch as $teamId) {
                $response = $responses[$teamId] ?? null;

                if (!$response instanceof Response || !$response->successful()) {
                    $this->logFailure('pouleindelingen', $response);

                    continue;
                }

                $url = $this->standingsUrlFrom((array) $response->json('hydra:member', []));

                if ($url !== null) {
                    $urls[$teamId] = $url;
                }
            }
        }

        return $urls;
    }

    public static function normalizeClubCode(string $code): string
    {
        $code = Str::upper(trim($code));

        if (preg_match('/^[A-Z0-9]{4,12}$/', $code) !== 1) {
            throw new NevoboUnavailable(__('Dit is geen geldige Nevobo-verenigingscode.'));
        }

        return $code;
    }

    /** @return list<NevoboClub> */
    private function fetchClubs(): array
    {
        $first = $this->get('/relatiebeheer/verenigingen', ['page' => 1]);
        $lastPage = $this->pageNumber($first->json('hydra:view.hydra:last')) ?? 1;
        $members = (array) $first->json('hydra:member', []);

        $pages = range(2, max(2, min($lastPage, self::MAX_PAGES)));
        if ($lastPage < 2) {
            $pages = [];
        }

        foreach (array_chunk($pages, self::CONCURRENCY) as $batch) {
            $responses = Http::pool(fn (Pool $pool): array => array_map(
                fn (int $page): mixed => $this->configure($pool->as((string) $page))
                    ->get(self::BASE_URL.'/relatiebeheer/verenigingen', ['page' => $page]),
                $batch,
            ));

            foreach ($batch as $page) {
                $response = $responses[(string) $page] ?? null;

                // One slow page (or a 429) should not throw away the rest:
                // it gets a second, sequential try with back-off.
                if (!$response instanceof Response || !$response->successful()) {
                    $response = $this->get('/relatiebeheer/verenigingen', ['page' => $page]);
                }

                array_push($members, ...(array) $response->json('hydra:member', []));
            }
        }

        $clubs = [];
        foreach ($members as $member) {
            $club = $this->clubFrom((array) $member);

            if ($club !== null) {
                $clubs[$club['code']] = $club;
            }
        }

        return array_values($clubs);
    }

    /** @return list<NevoboTeam> */
    private function fetchTeams(string $clubCode): array
    {
        $members = [];
        $query = ['vereniging' => '/relatiebeheer/verenigingen/'.Str::lower($clubCode)];

        // The page is passed as a parameter rather than following hydra:next:
        // a query array replaces the query string of the URL, which silently
        // dropped the club filter.
        for ($page = 1, $lastPage = 1; $page <= min($lastPage, self::MAX_PAGES); $page++) {
            $response = $this->get('/competitie/teams', $query + ['page' => $page]);
            array_push($members, ...(array) $response->json('hydra:member', []));
            $lastPage = $this->pageNumber($response->json('hydra:view.hydra:last')) ?? $page;
        }

        // Only this club's teams, whatever the API returns.
        $prefix = '/competitie/teams/'.Str::lower($clubCode).'/';
        $members = array_filter($members, static fn (mixed $member): bool => is_array($member)
            && str_starts_with((string) ($member['@id'] ?? ''), $prefix));

        $teams = [];
        foreach ($members as $member) {
            $team = $this->teamFrom((array) $member);

            if ($team !== null) {
                $teams[$team['nevobo_id']] = $team;
            }
        }

        return array_values($teams);
    }

    /** @return NevoboClub|null */
    private function clubFrom(array $member): ?array
    {
        $code = Str::upper(trim((string) ($member['organisatiecode'] ?? '')));
        $name = trim((string) ($member['naam'] ?? ''));

        if ($code === '' || $name === '' || $this->isDissolved($member['opheffing'] ?? null)) {
            return null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'official_name' => trim((string) ($member['officielenaam'] ?? '')) ?: $name,
            'city' => trim((string) ($member['vestigingsplaats'] ?? '')),
            'region' => Str::of((string) ($member['regio'] ?? ''))->afterLast('/')->replace('regio-', '')->replace('-', ' ')->title()->toString(),
        ];
    }

    /** @return NevoboTeam|null */
    private function teamFrom(array $member): ?array
    {
        $id = (string) ($member['@id'] ?? '');
        $name = trim((string) ($member['naam'] ?? ''));
        $code = NevoboTeamCode::fromNevobo($id, $name);
        $parsed = NevoboTeamCode::parseId($id);

        if ($code === null || $parsed === null) {
            return null;
        }

        // The export path uses the upper-case club code: /export/team/CNH8Q1U/heren/1/programma.rss.
        $clubCode = Str::upper(Str::of($id)->after('/competitie/teams/')->before('/')->toString());
        $exportBase = self::BASE_URL."/export/team/{$clubCode}/{$parsed['type']}/{$parsed['number']}";
        $standing = trim((string) ($member['standpositietekst'] ?? '')) ?: null;

        return [
            'nevobo_id' => $id,
            'name' => $name ?: $code,
            'code' => $code,
            'category' => TeamCode::category($code),
            'standing_text' => $standing,
            'regular' => NevoboTeamCode::isRegularCompetition($id, $standing),
            'schedule_url' => $this->exportUrl($member['_links']['download_programma_rss']['href'] ?? null) ?? "{$exportBase}/programma.rss",
            'results_url' => $this->exportUrl($member['_links']['download_resultaat_rss']['href'] ?? null) ?? "{$exportBase}/resultaten.rss",
        ];
    }

    /**
     * The stand.rss of the last competition pool; cup pools
     * ("bekertoernooi") have no meaningful standings for the season.
     *
     * @param  array<int, mixed>  $placements
     */
    private function standingsUrlFrom(array $placements): ?string
    {
        $poule = null;

        foreach ($placements as $placement) {
            $candidate = (string) (is_array($placement) ? ($placement['poule'] ?? '') : '');

            if (preg_match('#^/competitie/poules/[a-z0-9-]+/competitie-[a-z0-9-]+/[a-z0-9-]+$#i', $candidate) === 1) {
                $poule = $candidate;
            }
        }

        return $poule === null
            ? null
            : self::BASE_URL.'/export/poule/'.Str::after($poule, '/competitie/poules/').'/stand.rss';
    }

    /** Only paths on the Nevobo API; a version query (ics) is dropped. */
    private function exportUrl(mixed $href): ?string
    {
        if (!is_string($href) || preg_match('#^/export/team/[A-Z0-9]+/[a-z0-9-]+/\d+/[a-z]+\.rss$#i', $href) !== 1) {
            return null;
        }

        return self::BASE_URL.$href;
    }

    private function isDissolved(mixed $dissolvedAt): bool
    {
        if (!is_string($dissolvedAt) || $dissolvedAt === '') {
            return false;
        }

        try {
            return Carbon::parse($dissolvedAt)->isPast();
        } catch (Throwable) {
            return false;
        }
    }

    private function pageNumber(mixed $url): ?int
    {
        return is_string($url) && preg_match('/[?&]page=(\d+)/', $url, $match) === 1 ? (int) $match[1] : null;
    }

    /**
     * One request with a short retry for rate limiting and connection hiccups.
     *
     * @param  array<string, mixed>  $query
     *
     * @throws NevoboUnavailable
     */
    private function get(string $path, array $query = []): Response
    {
        try {
            $response = $this->configure(Http::createPendingRequest())
                ->retry(
                    [500, 1500],
                    when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && in_array($exception->response->status(), [429, 502, 503, 504], true)),
                    throw: false,
                )
                ->get(self::BASE_URL.$path, $query);
        } catch (ConnectionException) {
            $this->logFailure($path, null);

            throw NevoboUnavailable::make();
        }

        if (!$response->successful()) {
            $this->logFailure($path, $response);

            throw NevoboUnavailable::make($response->status());
        }

        return $response;
    }

    private function configure(PendingRequest $request): PendingRequest
    {
        return $request->timeout(10)->connectTimeout(5)
            ->withOptions(['allow_redirects' => false])
            ->accept('application/ld+json')
            ->withUserAgent(config('app.name').'/1.0');
    }

    private function logFailure(string $path, mixed $response): void
    {
        Log::warning('Nevobo API request failed', [
            'path' => $path,
            'status' => $response instanceof Response ? $response->status() : null,
            'error' => $response instanceof Throwable ? $response::class : null,
        ]);
    }
}
