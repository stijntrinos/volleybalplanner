<?php

namespace App\Planner\Http\Controllers;

use App\Planner\Models\FixtureMatch;
use App\Planner\Services\PublishedFeedService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The public RSS feeds of a club (module rss_feeds, checked by the route
 * middleware). The route's {club} parameter is resolved by the club
 * middleware, so the actions take it as their first argument and ignore it.
 */
class RssFeedController
{
    public function __construct(
        private readonly PublishedFeedService $feeds,
        private readonly CurrentClub $currentClub,
    ) {}

    public function central(): Response
    {
        return response($this->feeds->centralXml(), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function home(): Response
    {
        return $this->response(
            __(':club thuiswedstrijden', ['club' => $this->clubName()]),
            __('Gepubliceerde thuiswedstrijden'),
            $this->feeds->matches(),
        );
    }

    public function venue(mixed $club, string $name): Response
    {
        $matches = $this->feeds->matches()
            ->filter(fn (FixtureMatch $match): bool => mb_strtolower($match->venue?->name ?? '') === mb_strtolower($name));

        return $this->response(
            __(':club wedstrijden in :venue', ['club' => $this->clubName(), 'venue' => $name]),
            __('Gepubliceerde wedstrijden in :venue', ['venue' => $name]),
            $matches,
        );
    }

    public function team(mixed $club, string $code): Response
    {
        $pattern = preg_quote($code, '/');
        $matches = $this->feeds->matches()
            ->filter(fn (FixtureMatch $match): bool => preg_match(
                "/(?:^|[^A-Z0-9]){$pattern}(?:$|[^A-Z0-9])/i",
                $match->home_team_name.' '.$match->away_team_name,
            ) === 1);

        return $this->response(
            __(':club teamwedstrijden', ['club' => $this->clubName()]),
            __('Gepubliceerde wedstrijden voor het geselecteerde team'),
            $matches,
        );
    }

    public function day(mixed $club, string $date): Response
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, 404);

        // Filtering happens in the query: `date` is a Carbon cast, so a plain
        // comparison on the collection never matched and always produced an
        // empty day feed.
        return $this->response(
            __(':club wedstrijden :date', ['club' => $this->clubName(), 'date' => $date]),
            __('Gepubliceerde wedstrijden op :date', ['date' => $date]),
            $this->feeds->matches($date),
        );
    }

    private function clubName(): string
    {
        return $this->currentClub->require()->name;
    }

    /** @param  Collection<int, FixtureMatch>  $matches */
    private function response(string $title, string $description, Collection $matches): Response
    {
        return response($this->feeds->xml($title, $description, $matches->values()), 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
