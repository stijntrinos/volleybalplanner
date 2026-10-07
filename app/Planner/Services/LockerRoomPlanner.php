<?php

namespace App\Planner\Services;

use Illuminate\Support\Str;

/**
 * Assigns locker rooms to the home and away side of each match. Men use room
 * 1 (home) and 2 (away), women and youth girls 3/4 (home) and 5/6 (away).
 * Every match occupies its rooms from 15 minutes before to 15 minutes after.
 * When all preferred rooms are taken the first one is assigned anyway, with a
 * warning.
 */
class LockerRoomPlanner
{
    public const SIDE_HOME = 'home';

    public const SIDE_AWAY = 'away';

    private const GROUP_MEN = 'men';

    private const GROUP_WOMEN = 'women';

    /** Team locker_room_category values that mean the men's rooms. */
    private const MEN_CATEGORIES = ['men', 'boys', 'boys_men', 'heren', 'jongens', 'jongens_heren'];

    /** Team locker_room_category values that mean the women's rooms. */
    private const WOMEN_CATEGORIES = ['women', 'girls', 'mixed', 'girls_women', 'dames', 'meiden', 'gemengd', 'meiden_dames'];

    /**
     * @param  iterable<array{id: int|string, start: string, end: string, home: string, away: string, home_category?: ?string}>  $matches
     * @return array{
     *     assignments: array<int|string, array{home: int, away: int}>,
     *     warnings: list<array{match_id: int|string, side: string, team: string, room: int, reason: string}>
     * }
     */
    public function plan(iterable $matches): array
    {
        $occupied = [];
        $assignments = [];
        $warnings = [];
        $matches = collect($matches)->sortBy(fn (array $match): string => $match['start'].' '.$match['home']);

        foreach ($matches as $match) {
            $interval = ['start' => $this->minutes($match['start']) - 15, 'end' => $this->minutes($match['end']) + 15];
            $assignments[$match['id']] = [
                self::SIDE_HOME => $this->assign($match, self::SIDE_HOME, $interval, $occupied, $warnings),
                self::SIDE_AWAY => $this->assign($match, self::SIDE_AWAY, $interval, $occupied, $warnings),
            ];
        }

        return ['assignments' => $assignments, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>  $match
     * @param  array{start: int, end: int}  $interval
     * @param  array<int, list<array{start: int, end: int}>>  $occupied
     * @param  list<array<string, mixed>>  $warnings
     */
    private function assign(array $match, string $side, array $interval, array &$occupied, array &$warnings): int
    {
        $team = $match[$side];
        $category = $side === self::SIDE_HOME ? ($match['home_category'] ?? null) : null;
        $group = $this->group($team, $category);
        $rooms = $side === self::SIDE_HOME
            ? ($group === self::GROUP_MEN ? [1] : [3, 4])
            : ($group === self::GROUP_MEN ? [2] : [5, 6]);

        foreach ($rooms as $room) {
            if (!collect($occupied[$room] ?? [])->contains(fn (array $taken): bool => $interval['start'] < $taken['end'] && $taken['start'] < $interval['end'])) {
                $occupied[$room][] = $interval;

                return $room;
            }
        }

        $room = $rooms[0];
        $occupied[$room][] = $interval;
        $warnings[] = [
            'match_id' => $match['id'], 'side' => $side, 'team' => $team, 'room' => $room,
            'reason' => count($rooms) > 1
                ? __('Alle voorkeurskleedkamers zijn op dit tijdsblok bezet.')
                : __('Kleedkamer :room is op dit tijdsblok bezet.', ['room' => $room]),
        ];

        return $room;
    }

    private function group(string $team, ?string $override): string
    {
        $category = Str::lower(trim((string) $override));
        if (in_array($category, self::MEN_CATEGORIES, true)) {
            return self::GROUP_MEN;
        }
        if (in_array($category, self::WOMEN_CATEGORIES, true)) {
            return self::GROUP_WOMEN;
        }

        return preg_match('/\b(HS|HR|J[ABCD])\s*\d/i', $team) ? self::GROUP_MEN : self::GROUP_WOMEN;
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $hour * 60 + $minute;
    }
}
