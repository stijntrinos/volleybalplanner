<?php

namespace App\Planner\Services;

use App\Planner\Models\PlannerSetting;
use App\Planner\Models\TeamSource;
use App\Planner\Support\MatchCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Fetches and parses Nevobo RSS feeds (a team's schedule, a pool programme).
 * Responses are cached per URL for the club's cache TTL.
 */
class NevoboRssService
{
    /**
     * @return array{ok: bool, items?: list<array<string, mixed>>, reason?: string, cached?: bool}
     *
     * @throws InvalidArgumentException when the URL is not an allowed https URL
     */
    public function fetch(TeamSource $source, bool $force = false): array
    {
        $this->validateUrl((string) $source->url);
        $ttl = (int) PlannerSetting::current()->refresh()->cache_ttl_minutes ?: 15;
        $key = 'planner-nevobo-rss:'.hash('sha256', (string) $source->url);
        if ($force) {
            Cache::forget($key);
        }

        try {
            $fromCache = Cache::has($key);
            $items = Cache::remember($key, now()->addMinutes($ttl), function () use ($source): array {
                $response = Http::timeout(10)->accept('application/rss+xml, application/xml, text/xml')
                    ->withUserAgent(config('app.name').'/1.0')->get($source->url)->throw();

                return $this->parse($response->body(), (string) $source->url);
            });
            $source->update([
                'last_checked_at' => now(), 'last_succeeded_at' => now(), 'last_error' => null,
            ]);

            return ['ok' => true, 'items' => $items, 'cached' => $fromCache];
        } catch (Throwable $exception) {
            $source->update(['last_checked_at' => now(), 'last_error' => $exception->getMessage()]);

            return ['ok' => false, 'reason' => $exception->getMessage()];
        }
    }

    /**
     * @return list<array{
     *     code: ?string, date: string, time: string, home_team: string, away_team: string,
     *     location: ?string, pool: ?string, source_url: string, cancelled: bool
     * }>
     */
    public function parse(string $xml, string $url): array
    {
        preg_match_all('/<item\b[\s\S]*?<\/item>/i', $xml, $blocks);

        return collect($blocks[0])->map(function (string $block) use ($url): ?array {
            $get = function (string $tag) use ($block): ?string {
                if (!preg_match("/<{$tag}[^>]*>([\\s\\S]*?)<\\/{$tag}>/i", $block, $match)) {
                    return null;
                }

                return trim(html_entity_decode(strip_tags(preg_replace('/<!\[CDATA\[([\s\S]*?)\]\]>/', '$1', $match[1]))));
            };
            $title = $get('title') ?? '';
            $description = $get('description') ?? '';
            // Current Nevobo feeds sometimes put the date and time before the
            // team names in the title (e.g. "5 sep 15:00: Team A - Team B").
            // A cancelled match gets "Vervallen: " in front. Without removing
            // that too, the whole title stayed as the home team and a match of
            // "Vervallen: 17 okt 9:00: CLUB MC 5" appeared.
            $matchTitle = preg_replace(
                '/^\s*(?:Vervallen\s*:\s*)?\d{1,2}\s+[\p{L}.]+(?:\s+\d{4})?\s+\d{1,2}:\d{2}\s*:\s*/iu',
                '',
                $title,
            ) ?: $title;
            // A club name sometimes contains a dash itself ("WRA Juristen -
            // Rebelle HS 2"); splitting on the first dash made that "WRA
            // Juristen" against "Rebelle HS 2 - CLUB HS 3". A team name ends
            // in its code, so the dash after it separates the two teams. When
            // the title has no recognisable code, the first dash separates.
            preg_match('/^(.*?\b(?:HS|DS|JA|MA|JB|MB|JC|MC|H|D)\s*\d+\+?)\s+-\s+(.*?)\s*(?:\(|$)/i', $matchTitle, $teams)
                || preg_match('/^(.*?)\s+-\s+(.*?)\s*(?:\(|$)/', $matchTitle, $teams);
            preg_match('/\(([A-Z0-9-]+)\s*[A-Z]?\)/i', $title, $codeFromTitle);
            // The current Nevobo feed does not put the match code in the
            // title but in the description as "Wedstrijd: 50003CD   DG".
            // Without it the import fell back to the team names. The same
            // teams meet several times, so the wrong meeting then showed up
            // as moved on the dashboard.
            preg_match('/\bWedstrijd\s*:\s*([A-Z0-9-]+(?:\s+[A-Z0-9-]+)*)\s*(?:,|$)/iu', $description, $codeFromDescription);
            $code = $codeFromTitle[1] ?? $codeFromDescription[1] ?? null;
            // The feed writes the code in a fixed-width field; the planning
            // file uses the federation notation. Without that conversion the
            // nightly sync matches nothing and duplicates the whole planning.
            $code = MatchCode::normalize($code);
            preg_match('/(\d{1,2})[-\/]([0-9]{1,2})[-\/](\d{4})/', $description, $date);
            preg_match('/(\d{1,2}):(\d{2})/', $description, $time);
            if (!$date && ($pubDate = $get('pubDate'))) {
                try {
                    $parsed = now()->parse($pubDate);
                    $date = [null, $parsed->day, $parsed->month, $parsed->year];
                    $time = [null, $parsed->hour, sprintf('%02d', $parsed->minute)];
                } catch (Throwable) {
                }
            }
            if (!$date) {
                return null;
            }
            // After the venue follow street, postcode and city. Those address
            // details are not part of the venue name; if they stayed, every
            // sync seemed to contain a venue change.
            preg_match('/(?:Speellocatie|Locatie|Sporthal|Zaal)\s*:\s*([^,\n<|]+)/i', $description, $location);
            preg_match('/(?:Poule|Klasse)[:\s]+([^\n<|]+)/i', $description, $pool);

            return [
                'code' => $code,
                'date' => sprintf('%04d-%02d-%02d', $date[3], $date[2], $date[1]),
                'time' => $time ? sprintf('%02d:%02d', $time[1], $time[2]) : '00:00',
                'home_team' => trim($teams[1] ?? $matchTitle), 'away_team' => trim($teams[2] ?? ''),
                'location' => trim($location[1] ?? '') ?: null, 'pool' => trim($pool[1] ?? '') ?: null,
                'source_url' => $url,
                'cancelled' => (bool) preg_match('/^\s*Vervallen\s*:/iu', $title),
            ];
        })->filter()->values()->all();
    }

    private function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw new InvalidArgumentException(__('Alleen geldige HTTPS-feed-URL’s zijn toegestaan.'));
        }
        $host = $parts['host'];
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            throw new InvalidArgumentException(__('Lokale feed-URL’s zijn niet toegestaan.'));
        }
    }
}
