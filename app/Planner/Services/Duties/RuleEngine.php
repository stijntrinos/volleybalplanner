<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\Team;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;

/**
 * Steps 1 and 2 of the planning rules: who can do the duty (hard rules) and
 * who is not proposed (soft rules). A team that breaks a hard rule is never
 * proposed and blocks applying. A team that breaks a soft rule can do the
 * duty; the planning committee can deliberately choose it.
 *
 * The duties are the volunteer duties teams do at home matches (referee,
 * scorer, hall duty); VolleyStars duties follow the same rules.
 */
class RuleEngine
{
    /** How a soft rule starts in the check and in the team choice. */
    public const NOT_PROPOSED = 'Niet voorgesteld: :reason';

    /**
     * Whether a reason allows a deliberate choice by the planning committee.
     * Such reasons are shown, but do not block applying.
     */
    public static function isWarning(string $reason): bool
    {
        return str_starts_with($reason, __(self::NOT_PROPOSED, ['reason' => '']));
    }

    public static function notProposed(string $reason): string
    {
        return __(self::NOT_PROPOSED, ['reason' => $reason]);
    }

    /**
     * All reasons for the check: first the hard rules, then the soft rules
     * with "Niet voorgesteld:" in front.
     *
     * @return list<string>
     */
    public function violations(Team $team, array $duty, PlanningContext $context): array
    {
        return [
            ...$this->hard($team, $duty, $context),
            ...array_map(self::notProposed(...), $this->soft($team, $duty, $context)),
        ];
    }

    /**
     * Step 1: who can do the duty.
     *
     * @return list<string>
     */
    public function hard(Team $team, array $duty, PlanningContext $context): array
    {
        $reasons = [];
        if (!$team->include_in_duties) {
            $reasons[] = __('Het team doet niet mee aan de FTZ-planning.');
        }

        $date = $duty['date'];
        $start = PlayingRounds::minutes($duty['start']);
        $end = PlayingRounds::minutes($duty['end']);
        $own = $context->ownMatches($team, $date);

        // H1: an away match outside the home city takes the whole day.
        if ($own->contains(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Away && !$context->isInHomeCity($match))) {
            $city = $context->homeCity();
            $reasons[] = $city !== ''
                ? __('Het team speelt die dag uit buiten :city.', ['city' => $city])
                : __('Het team heeft die dag een uitwedstrijd.');
        }

        $local = $own->filter(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Home || $context->isInHomeCity($match));

        // H2: an own match in another venue.
        if ($duty['venue_id'] !== null
            && $local->contains(fn (FixtureMatch $match): bool => $match->venue_id !== null && !$context->sameVenue($match->venue_id, $duty['venue_id']))) {
            $reasons[] = __('Het team speelt die dag in een andere sporthal.');
        }

        // H3: an own match at the same time. A team that scores its own match
        // may do the scorer duty of that match: the scorer comes from the bench.
        $target = $duty['match'] ?? null;
        $scoresItself = $duty['duty'] === PlanningContext::DUTY_SCORER && $target !== null && $team->scoresOwnMatchOn($date);
        $clashing = $local->filter(fn (FixtureMatch $match): bool => $this->overlap(
            $start, $end,
            PlayingRounds::minutes($match->time), PlayingRounds::minutes($match->time) + (int) $match->duration_minutes,
        ) && !($scoresItself && $match->id === $target->id));
        if ($clashing->isNotEmpty()) {
            $reasons[] = __('Het team speelt zelf op het tijdstip van de dienst.');
        }

        // H4: at the same time already a duty in another venue. In the same one is fine.
        foreach ($context->register->ofTeamOnDay($team->code, $date, $duty['key']) as $other) {
            if ($this->overlap($start, $end, PlayingRounds::minutes($other['start']), PlayingRounds::minutes($other['end']))
                && !$context->sameVenue($other['venue_id'], $duty['venue_id'])) {
                $reasons[] = __('Het team heeft op dat moment al een dienst in een andere sporthal.');
                break;
            }
        }

        // H5: scorer restrictions.
        if ($duty['duty'] === PlanningContext::DUTY_SCORER && $target !== null) {
            $heavy = $context->rules->scorerHeavyTeams();
            if ($team->no_scoring_heavy_teams && in_array($context->homeCode($target), $heavy, true)) {
                $reasons[] = __('Telt niet bij :teams.', ['teams' => DutyRules::enumerate($heavy)]);
            }
            if ($team->no_scoring_late && PlayingRounds::minutes($target->time) >= $context->rules->scorerCutoffMinutes()) {
                $reasons[] = __('Telt niet bij wedstrijden vanaf :time uur.', ['time' => $context->rules->scorerCutoff()]);
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Step 2: who is not proposed.
     *
     * @return list<string>
     */
    public function soft(Team $team, array $duty, PlanningContext $context): array
    {
        $reasons = [];
        $date = $duty['date'];
        $type = $duty['duty'];
        $start = PlayingRounds::minutes($duty['start']);
        $end = PlayingRounds::minutes($duty['end']);
        $target = $duty['match'] ?? null;
        $playsThisSlot = $context->playsItself($team, $target);
        $class = TeamCode::youthClass($team->code);

        // U1: youth not on weekdays, except scoring its own match.
        $weekday = Weekday::number($date);
        if ($class !== null && $weekday >= 1 && $weekday <= 5
            && !($type === PlanningContext::DUTY_SCORER && $playsThisSlot && $team->scoresOwnMatchOn($date))) {
            $reasons[] = __('Jeugdteams krijgen doordeweeks geen diensten.');
        }

        // U2: a reported absence.
        foreach ($context->absencesOf($team) as $absence) {
            if ($absence->covers($date, $duty['start'], $duty['end'])) {
                $reasons[] = $absence->description();
                break;
            }
        }

        // U3: training at the time of the duty. Whoever plays at that moment
        // does not train: the training gives way to the match.
        if (!$playsThisSlot) {
            foreach ($context->trainingsOf($team, $date) as $training) {
                if ($this->overlap($start, $end, PlayingRounds::minutes($training->start_time), PlayingRounds::minutes($training->end_time))) {
                    $reasons[] = __('Het team traint op dat moment.');
                    break;
                }
            }
        }

        // U4: weekly maximum.
        $maximum = $team->weekly_max ?? (int) $context->settings->max_duties_per_week;
        if ($maximum > 0 && $context->register->countInWeek($team->code, $date, $duty['key']) >= $maximum) {
            $reasons[] = __('Het weekmaximum van :max diensten is bereikt.', ['max' => $maximum]);
        }

        // U5: at most one venue per day.
        foreach ($context->register->ofTeamOnDay($team->code, $date, $duty['key']) as $other) {
            if ($other['venue_id'] !== null && $duty['venue_id'] !== null && !$context->sameVenue($other['venue_id'], $duty['venue_id'])) {
                $reasons[] = __('Het team heeft die dag al een dienst in een andere sporthal.');
                break;
            }
        }

        if ($type === PlanningContext::DUTY_HALL && $team->no_hall_duty) {
            $reasons[] = __('Krijgt geen zaaldiensten voorgesteld.');
        }

        if ($type === PlanningContext::DUTY_VOLLEYSTARS && $duty['role'] === VolleystarsRole::Scoring && $class !== 'B') {
            $reasons[] = __('Het tellen bij VolleyStars doet een B-jeugdteam.');
        }

        // U8, and U9 here too: refereeing at VolleyStars is a referee duty.
        if ($type === PlanningContext::DUTY_VOLLEYSTARS && $duty['role'] === VolleystarsRole::Refereeing) {
            if (!TeamCode::isSeniors($team->code) && $class !== 'A') {
                $reasons[] = __('Het fluiten bij VolleyStars doen senioren of A-jeugd.');
            }
            if ($team->never_referees) {
                $reasons[] = __('Fluit nooit.');
            }
        }

        if ($type === PlanningContext::DUTY_REFEREE && $target !== null) {
            array_push($reasons, ...$this->refereeRules($team, $class, $target, $context));
        }

        return array_values(array_unique($reasons));
    }

    /** @return list<string> U9 to U12 */
    private function refereeRules(Team $team, ?string $class, FixtureMatch $target, PlanningContext $context): array
    {
        $reasons = [];
        $homeCode = $context->homeCode($target);
        $homeClass = TeamCode::youthClass($homeCode);
        $restricted = $context->rules->restrictedRefereeTeams();

        if ($team->never_referees) {
            $reasons[] = __('Fluit nooit.');
        }
        if ($class === 'A' && !$team->referees_c_youth) {
            $reasons[] = __('Van de A-jeugd fluiten alleen teams met \'Fluit bij C-jeugd\'.');
        }
        if ($team->referees_c_youth && ($homeClass === 'A' || TeamCode::isSeniors($homeCode))) {
            $reasons[] = __('Fluit niet bij A-jeugd en senioren.');
        }
        if (in_array($homeCode, $restricted, true) && !$team->referees_restricted_teams) {
            $reasons[] = __('Bij :teams fluiten alleen de teams die daarvoor zijn aangewezen.', ['teams' => DutyRules::enumerate($restricted)]);
        }
        if ($team->referees_youth_and_lower_women && $homeCode !== ''
            && $homeClass === null && !$this->isLowerWomen($homeCode, $target, $context)) {
            $reasons[] = __('Fluit alleen bij jeugd en dames uit een lagere klasse.');
        }

        return $reasons;
    }

    private function isLowerWomen(string $homeCode, FixtureMatch $target, PlanningContext $context): bool
    {
        return preg_match('/^(DS|D)\d+$/', $homeCode) === 1
            && $context->rules->isLowerClass($target->pool.' '.$target->home_team_name);
    }

    private function overlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }
}
