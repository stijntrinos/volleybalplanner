<?php

namespace App\Planner\Services\Duties;

use App\Planner\Models\FixtureMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Time slot helpers for the duty planner: the slot of a single match and the
 * hall duty rounds of a playing day per venue.
 */
final class DutySlots
{
    /** Matches starting within this many minutes of each other share one hall duty round. */
    private const ROUND_GAP_MINUTES = 20;

    /** The first hall duty of the day starts this long before the first match. */
    private const SETUP_MINUTES = 45;

    /** The last hall duty ends this long after the last match. */
    private const TEARDOWN_MINUTES = 30;

    /** @return array{date: string, start: string, end: string, venue_id: ?int, match: FixtureMatch} */
    public static function forMatch(FixtureMatch $match): array
    {
        $start = substr((string) $match->time, 0, 5);

        return [
            'date' => $match->date->toDateString(),
            'start' => $start,
            'end' => CarbonImmutable::createFromFormat('H:i', $start)->addMinutes((int) $match->duration_minutes)->format('H:i'),
            'venue_id' => $match->venue_id,
            'match' => $match,
        ];
    }

    /**
     * Matches grouped by playing date. The rules only look at the slot's
     * day; without this index every candidate scanned the whole season again.
     *
     * @return Collection<string, Collection<int, FixtureMatch>>
     */
    public static function byDate(Collection $matches): Collection
    {
        return $matches->groupBy(fn (FixtureMatch $match): string => (string) $match->date?->toDateString());
    }

    /**
     * The hall duty rounds per date and venue: matches starting close
     * together form one round, which runs until the next round starts.
     *
     * @return list<array{slot: array{date: string, venue_id: ?int, venue: ?string, start: string, end: string}, matches: Collection<int, FixtureMatch>}>
     */
    public static function playingRounds(Collection $matches): array
    {
        $result = [];
        foreach ($matches->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString().'|'.$match->venue_id) as $group) {
            $rounds = [];
            foreach ($group->sortBy('time')->values() as $match) {
                $minutes = self::minutes($match->time);
                $last = array_key_last($rounds);
                if ($last === null || abs($minutes - $rounds[$last]['base']) > self::ROUND_GAP_MINUTES) {
                    $rounds[] = ['base' => $minutes, 'matches' => collect([$match])];
                } else {
                    $rounds[$last]['matches']->push($match);
                }
            }
            foreach ($rounds as $index => $round) {
                $first = $round['matches']->first();
                $startMinutes = $index === 0 ? $round['base'] - self::SETUP_MINUTES : $round['base'];
                $endMinutes = $rounds[$index + 1]['base']
                    ?? $round['matches']->max(fn (FixtureMatch $match): int => self::minutes($match->time) + $match->duration_minutes) + self::TEARDOWN_MINUTES;
                $result[] = ['slot' => [
                    'date' => $first->date->toDateString(), 'venue_id' => $first->venue_id,
                    'venue' => $first->venue?->name,
                    'start' => self::time($startMinutes), 'end' => self::time($endMinutes),
                ], 'matches' => $round['matches']];
            }
        }

        return $result;
    }

    /** Identifies a hall duty by date, venue and start time. */
    public static function hallDutyKey(string $date, ?int $venueId, string $start): string
    {
        return $date.'|'.$venueId.'|'.substr($start, 0, 5);
    }

    public static function minutes(string $time): int
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', substr($time, 0, 5))), 2, 0);

        return $hour * 60 + $minute;
    }

    public static function time(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
