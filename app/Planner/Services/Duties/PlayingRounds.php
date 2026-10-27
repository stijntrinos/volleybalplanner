<?php

namespace App\Planner\Services\Duties;

use App\Planner\Models\FixtureMatch;
use Illuminate\Support\Collection;

/**
 * The rounds per playing day and venue. Matches starting within twenty
 * minutes of each other form one round; every round gets a hall duty. The
 * rules about adjoining duties and "right before the own match" work with
 * the round number.
 */
final class PlayingRounds
{
    /** Matches starting within this many minutes of each other share one round. */
    private const ROUND_GAP_MINUTES = 20;

    /** The first hall duty of the day starts this long before the first match. */
    private const SETUP_MINUTES = 45;

    /** The last hall duty ends this long after the last match. */
    private const TEARDOWN_MINUTES = 30;

    /** @var list<array{slot: array{date: string, venue_id: int, venue: ?string, start: string, end: string, round: int}, matches: Collection<int, FixtureMatch>}> */
    private array $rounds = [];

    /** @var array<int, int> match id => round number */
    private array $perMatch = [];

    /** @var array<string, list<array{start: int, end: int, round: int}>> date|venue => windows */
    private array $windows = [];

    /** @param Collection<int, FixtureMatch> $homeMatches */
    public function __construct(Collection $homeMatches)
    {
        $withVenue = $homeMatches->filter(fn (FixtureMatch $match): bool => $match->venue_id && $match->date && $match->time);

        foreach ($withVenue->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString().'|'.$match->venue_id) as $key => $group) {
            $groups = [];
            foreach ($group->sortBy('time')->values() as $match) {
                $minutes = self::minutes($match->time);
                $last = array_key_last($groups);
                if ($last === null || abs($minutes - $groups[$last]['base']) > self::ROUND_GAP_MINUTES) {
                    $groups[] = ['base' => $minutes, 'matches' => collect([$match])];
                } else {
                    $groups[$last]['matches']->push($match);
                }
            }

            foreach ($groups as $index => $round) {
                $first = $round['matches']->first();
                $start = $index === 0 ? $round['base'] - self::SETUP_MINUTES : $round['base'];
                $end = $groups[$index + 1]['base']
                    ?? $round['matches']->max(fn (FixtureMatch $match): int => self::minutes($match->time) + (int) $match->duration_minutes) + self::TEARDOWN_MINUTES;
                $this->rounds[] = ['slot' => [
                    'date' => $first->date->toDateString(), 'venue_id' => $first->venue_id,
                    'venue' => $first->venue?->name,
                    'start' => self::time($start), 'end' => self::time($end), 'round' => $index,
                ], 'matches' => $round['matches']];
                $this->windows[$key][] = ['start' => $start, 'end' => $end, 'round' => $index];
                foreach ($round['matches'] as $match) {
                    $this->perMatch[$match->id] = $index;
                }
            }
        }
    }

    /** @return list<array{slot: array{date: string, venue_id: int, venue: ?string, start: string, end: string, round: int}, matches: Collection<int, FixtureMatch>}> */
    public function all(): array
    {
        return $this->rounds;
    }

    public function ofMatch(?int $matchId): ?int
    {
        return $matchId === null ? null : ($this->perMatch[$matchId] ?? null);
    }

    /** The round a time in this venue falls in, or null outside the rounds. */
    public function at(string $date, ?int $venueId, ?string $time): ?int
    {
        if ($venueId === null || blank($time)) {
            return null;
        }

        $minutes = self::minutes($time);
        foreach ($this->windows[$date.'|'.$venueId] ?? [] as $window) {
            if ($minutes >= $window['start'] && $minutes < $window['end']) {
                return $window['round'];
            }
        }

        return null;
    }

    public static function minutes(?string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', substr((string) $time, 0, 5))), 2, 0);

        return $hour * 60 + $minute;
    }

    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
