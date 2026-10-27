<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\Team;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use Carbon\CarbonImmutable;

/**
 * Step 3 of the planning rules: who goes first. The teams that pass steps 1
 * and 2 are sorted on a row of keys (V0 to D7, in that order); a rule only
 * decides when the teams are equal on all rules above it. The generator
 * takes the top team; the team choice shows the whole list, with the teams
 * that dropped out at the bottom and their reason.
 */
final class TeamChoice
{
    public const PROPOSED = 'proposed';

    public const NOT_PROPOSED = 'not_proposed';

    public const CANNOT = 'cannot';

    public function __construct(private readonly RuleEngine $rules) {}

    /** The team the generator proposes for this duty. */
    public function best(array $duty, PlanningContext $context): ?Team
    {
        $best = null;
        $bestKey = null;
        foreach ($context->plannableTeams() as $team) {
            if ($this->rules->hard($team, $duty, $context) !== [] || $this->rules->soft($team, $duty, $context) !== []) {
                continue;
            }
            [$key] = $this->order($team, $duty, $context);
            if ($bestKey === null || self::compare($key, $bestKey) < 0) {
                $best = $team;
                $bestKey = $key;
            }
        }

        return $best;
    }

    /**
     * All teams for the team choice: first the proposed ones in the order of
     * step 3, then those not proposed, and at the bottom those that cannot do
     * the duty.
     *
     * @return list<array{team: Team, code: string, status: string, balance: PointsBalance, reasons: list<string>}>
     */
    public function candidates(array $duty, PlanningContext $context): array
    {
        $groups = [self::PROPOSED => 0, self::NOT_PROPOSED => 1, self::CANNOT => 2];
        $candidates = [];
        foreach ($context->plannableTeams() as $team) {
            $hard = $this->rules->hard($team, $duty, $context);
            $soft = $this->rules->soft($team, $duty, $context);
            [$key, $reasons] = $this->order($team, $duty, $context);
            $status = match (true) {
                $hard !== [] => self::CANNOT,
                $soft !== [] => self::NOT_PROPOSED,
                default => self::PROPOSED,
            };
            $candidates[] = [
                'team' => $team,
                'code' => (string) $team->code,
                'status' => $status,
                'balance' => $context->balanceWithout($team, $duty['key']),
                'reasons' => match ($status) {
                    self::CANNOT => $hard,
                    self::NOT_PROPOSED => array_map(RuleEngine::notProposed(...), $soft),
                    default => $reasons,
                },
                'order' => [$groups[$status], ...$key],
            ];
        }

        usort($candidates, fn (array $a, array $b): int => self::compare($a['order'], $b['order']));

        return array_map(fn (array $candidate): array => array_diff_key($candidate, ['order' => true]), $candidates);
    }

    /**
     * The sort key of step 3 and the short reasons the team choice shows
     * with it.
     *
     * @return array{0: list<int|float|string>, 1: list<string>}
     */
    public function order(Team $team, array $duty, PlanningContext $context): array
    {
        $settings = $context->settings;
        $type = $duty['duty'];
        $date = $duty['date'];
        $code = (string) $team->code;
        $round = $duty['round'];
        $points = max(1, (int) $duty['points']);
        $balance = $context->balanceWithout($team, $duty['key']);
        $arrears = $context->arrears($team, $date, $duty['key']);
        $below = $balance->isBelowMinimum();
        $youth = TeamCode::youthClass($code) !== null;
        $reasons = [];

        $day = $context->register->ofTeamOnDay($code, $date, $duty['key']);
        $hallOnDay = array_values(array_filter($day, fn (array $other): bool => $other['duty'] === PlanningContext::DUTY_HALL));
        $ownRounds = $context->ownMatches($team, $date)
            ->filter(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Home
                && $context->sameVenue($match->venue_id, $duty['venue_id']));
        $playsHereAtHome = $ownRounds->isNotEmpty();
        $ownRounds = $ownRounds->map(fn (FixtureMatch $match): ?int => $context->rounds->ofMatch($match->id))->filter(fn (?int $r): bool => $r !== null);
        $rightBeforeOwn = $round !== null && $ownRounds->contains($round + 1);
        $rightAfterOwn = $round !== null && $ownRounds->contains($round - 1);

        // V0: hall duty maximum per day (soft).
        $maxHall = (int) $settings->max_hall_duties_per_day;
        $v0 = $type === PlanningContext::DUTY_HALL && $maxHall > 0 && count($hallOnDay) >= $maxHall ? 1 : 0;
        if ($v0) {
            $reasons[] = __('Heeft die dag al :count zaaldiensten (maximum :max).', ['count' => count($hallOnDay), 'max' => $maxHall]);
        }

        // V1: finish a VolleyStars block.
        $v1 = $type === PlanningContext::DUTY_VOLLEYSTARS
            && collect($day)->contains(fn (array $other): bool => $other['block_id'] !== null && $other['block_id'] === $duty['block_id']) ? 0 : 1;

        // V2: B youth refereed by a team that preferably referees C youth.
        $homeClass = TeamCode::youthClass($context->homeCode($duty['match'] ?? null));
        $v2 = $type === PlanningContext::DUTY_REFEREE && $homeClass !== null && $homeClass !== 'C' && $team->referees_c_youth ? 1 : 0;
        if ($v2) {
            $reasons[] = __('Fluit bij voorkeur alleen C-jeugd; dit is een uitzondering.');
        }

        // V3: below the minimum goes first.
        $v3 = $below ? 0 : 1;

        // V4: "never referees" teams go first for scorer, hall and VolleyStars duties.
        $refereeDuty = $type === PlanningContext::DUTY_REFEREE || $duty['role'] === VolleystarsRole::Refereeing;
        $v4 = !$refereeDuty && $team->never_referees ? 0 : 1;

        // V5 and V6: youth scores right after and on its own playing day.
        $v5 = $type === PlanningContext::DUTY_SCORER && $youth && $rightAfterOwn ? 0 : 1;
        $v6 = $type === PlanningContext::DUTY_SCORER && $youth && $playsHereAtHome ? 0 : 1;

        // V7: home players around their own match.
        $v7 = $below && $playsHereAtHome && count($day) < (int) $settings->home_player_priority && !$rightBeforeOwn ? 0 : 1;

        // D1: no minimum.
        $d1 = $balance->hasMinimum() ? 0 : 1;
        if ($d1 && $context->withPoints) {
            $reasons[] = __('Geen minimum.');
        }

        // D2: not too many on one day; for hall duties preferably an adjoining block.
        $maxDay = (int) $settings->max_duties_per_day;
        $d2 = $maxDay > 0 && count($day) >= $maxDay ? 1 : 0;
        if ($d2) {
            $reasons[] = __('Heeft die dag al :count diensten (dagmaximum :max).', ['count' => count($day), 'max' => $maxDay]);
        }
        $d2Hall = 0;
        if ($type === PlanningContext::DUTY_HALL && $hallOnDay !== []) {
            $d2Hall = $this->adjoins($hallOnDay, $duty, $context) ? 0 : 2;
            if ($d2Hall === 2) {
                $reasons[] = __('Zaaldienst sluit niet aan op de andere zaaldienst die dag.');
            }
        } elseif ($type === PlanningContext::DUTY_HALL) {
            $d2Hall = 1;
        }

        // D3: fair share already reached.
        $d3 = $arrears !== null && $arrears <= 0 ? 1 : 0;
        if ($d3) {
            $reasons[] = __('Heeft het eerlijke deel voor deze seizoenshelft al.');
        }

        // D4: not right before the own match.
        $d4 = $rightBeforeOwn ? 1 : 0;
        if ($d4) {
            $reasons[] = __('De dienst valt vlak voor de eigen wedstrijd.');
        }

        // D5: arrears in bands, within that the playing group.
        $band = $arrears === null ? 0 : (int) floor($arrears / $points);
        $playingGroup = match (true) {
            $playsHereAtHome => 1,
            blank($team->playing_group) => 4,
            $this->playingGroupFits((string) $team->playing_group, $date) => 2,
            default => 3,
        };

        // D6: adjoining and with room in between.
        $d6 = 0;
        if ($day !== [] && !$this->adjoins($day, $duty, $context)) {
            $d6 = 1;
            $reasons[] = __('Niet aansluitend aan de andere diensten die dag.');
        }
        $gap = (int) $settings->min_days_between_duties;
        if ($gap > 0) {
            $nearest = collect($context->register->ofTeam($code, $duty['key']))
                ->filter(fn (array $other): bool => $other['date'] !== $date && PlanningContext::daysBetween($other['date'], $date) < $gap)
                ->sortBy(fn (array $other): int => PlanningContext::daysBetween($other['date'], $date))
                ->first();
            if ($nearest !== null) {
                $d6 = 1;
                $reasons[] = __('Heeft al een dienst op :date (binnen :days dagen).', [
                    'date' => CarbonImmutable::parse($nearest['date'])->format('d-m'),
                    'days' => $gap,
                ]);
            }
        }

        // D7: whoever is furthest behind.
        $d7Shortfall = in_array($type, [PlanningContext::DUTY_HALL, PlanningContext::DUTY_VOLLEYSTARS], true) ? -$balance->remaining() : 0;
        $d7Standing = $balance->hasMinimum() ? (float) $balance->percentage() : (float) $balance->total();

        return [[
            $v0, $v1, $v2, $v3, $v4, $v5, $v6, $v7,
            $d1, $d2, $d2Hall, $d3, $d4, -$band, $playingGroup, $d6,
            $d7Shortfall, -round($arrears ?? 0.0, 6), $d7Standing, $context->register->count($code, $duty['key']),
            $code,
        ], $reasons];
    }

    /**
     * Compares two sort keys. The last element is the team code:
     * case-insensitive and with numbers in order (DS 2 before DS 10).
     */
    public static function compare(array $a, array $b): int
    {
        $last = count($a) - 1;
        foreach ($a as $index => $value) {
            $outcome = $index === $last
                ? strnatcasecmp((string) $value, (string) $b[$index])
                : $value <=> $b[$index];
            if ($outcome !== 0) {
                return $outcome;
            }
        }

        return 0;
    }

    /**
     * Whether the duty adjoins one of the other duties: the same venue and
     * the same, previous or next round.
     *
     * @param  list<array<string, mixed>>  $others
     */
    private function adjoins(array $others, array $duty, PlanningContext $context): bool
    {
        foreach ($others as $other) {
            if (!$context->sameVenue($other['venue_id'], $duty['venue_id'])) {
                continue;
            }
            if ($other['round'] !== null && $duty['round'] !== null) {
                if (abs($other['round'] - $duty['round']) <= 1) {
                    return true;
                }

                continue;
            }
            // Without a round number (a VolleyStars duty outside the rounds) a
            // gap of at most half an hour still counts as adjoining.
            $start = PlayingRounds::minutes($duty['start']);
            $end = PlayingRounds::minutes($duty['end']);
            if (PlayingRounds::minutes($other['start']) <= $end + 30 && $start <= PlayingRounds::minutes($other['end']) + 30) {
                return true;
            }
        }

        return false;
    }

    private function playingGroupFits(string $playingGroup, string $date): bool
    {
        $weekday = Weekday::number($date);

        return match ($playingGroup) {
            Team::PLAYING_GROUP_WEEKDAYS => $weekday >= 1 && $weekday <= 5,
            Team::PLAYING_GROUP_SATURDAY => $weekday === 6,
            default => false,
        };
    }
}
