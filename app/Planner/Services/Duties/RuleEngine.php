<?php

namespace App\Planner\Services\Duties;

use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scores and validates a candidate duty assignment: the volunteer duties
 * teams take on during home matches (referee, scorer, hall duty).
 *
 * A slot is ['date' => Y-m-d, 'start' => H:i, 'end' => H:i, 'venue_id' => ?int,
 * 'match' => ?FixtureMatch].
 */
class RuleEngine
{
    /**
     * The generator does not assign C youth after this time, but the
     * planning committee may deliberately do so anyway -- sometimes there is
     * nobody else. It therefore stays a warning and does not block applying.
     */
    public const LATE_C_YOUTH = 'C-jeugd mag niet tellen vanaf :time.';

    /** @var array<int, DutyRules> */
    private array $rulesCache = [];

    /** @var array<string, int> */
    private array $minutesCache = [];

    /** @var array<string, string> */
    private array $codeCache = [];

    /** @var array<string, string> */
    private array $nameCache = [];

    /** @var array<string, Collection<int, FixtureMatch>> */
    private array $ownMatchesCache = [];

    /**
     * Whether a reason allows a deliberate choice by the planning committee.
     * Such reasons are shown, but do not count as an issue and therefore do
     * not block applying.
     */
    public static function isWarning(string $reason): bool
    {
        return fnmatch(str_replace(':time', '*', __(self::LATE_C_YOUTH)), $reason);
    }

    /**
     * @param  Collection<int, FixtureMatch>  $matches  the matches of (at least) the slot's day
     * @param  Collection<int, Training>  $trainings  the trainings of (at least) the slot's weekday
     * @return list<string>
     */
    public function violations(Team $team, DutyType $duty, array $slot, Collection $matches, Collection $trainings): array
    {
        $reasons = [];
        if (!$team->include_in_duties) {
            $reasons[] = __('Team is uitgesloten van de FTZ-planner.');
        }

        $date = $slot['date'];
        $start = $this->minutes($slot['start']);
        $end = $this->minutes($slot['end']);
        $ownMatches = $this->ownMatches($team, $date, $matches);

        if ($ownMatches->contains(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Away)) {
            $reasons[] = __('Het team heeft die dag een uitwedstrijd.');
        }
        // A team may score its own match: the scorer comes from the bench and
        // is not on court. With another simultaneous match that is not
        // possible, and refereeing your own match certainly is not.
        $playsThisSlot = $this->playsItself($team, $slot['match'] ?? null);
        $ownDuty = $duty === DutyType::Scorer && $playsThisSlot;
        $clashing = $ownMatches
            ->filter(fn (FixtureMatch $match): bool => $match->match_type === MatchType::Home
                && $this->overlap($start, $end, $this->minutes($match->time), $this->minutes($match->time) + $match->duration_minutes))
            ->when($ownDuty, fn (Collection $matches): Collection => $matches->where('id', '!=', $slot['match']->id));
        if ($clashing->isNotEmpty()) {
            $reasons[] = __('De taak overlapt met de eigen thuiswedstrijd.');
        }

        // A team that plays its own match at this moment does not train then:
        // the training in the calendar gives way to the match. The message
        // said nothing about the duty and blocked a valid scorer.
        if (!$playsThisSlot) {
            foreach ($trainings->where('team_id', $team->id)->where('weekday', Weekday::name($date)) as $training) {
                if ($training->venue_id !== $slot['venue_id']) {
                    $reasons[] = __('Het team traint die dag in een andere sporthal.');
                } elseif ($this->overlap($start, $end, $this->minutes($training->start_time), $this->minutes($training->end_time))) {
                    $reasons[] = __('De taak overlapt met de training.');
                }
            }
        }

        $code = $this->code($team);
        $rules = $this->rules();
        $target = $slot['match'] ?? null;
        $targetCode = $this->teamCodeFromName($target->home_team_name ?? '');
        if ($duty === DutyType::Referee) {
            if ($this->isBCYouth($code)) {
                $reasons[] = __('B- en C-jeugd mogen niet fluiten.');
            }
            if ($rules->mayNotReferee($code)) {
                $reasons[] = __(':team fluit niet.', ['team' => DutyRules::readable($code)]);
            }
            if ($this->isAYouth($code) && !$this->aYouthMayReferee($target, $rules)) {
                $reasons[] = __('A-jeugd mag alleen jeugd of een lagere klasse fluiten.');
            }
            if ($this->isLowerWomen($team, $rules) && !$this->isYouthMatch($target)) {
                $reasons[] = __('Dames uit een lagere klasse mag alleen jeugdwedstrijden fluiten.');
            }
            $allowed = $rules->refereeRequirement($targetCode);
            if ($allowed !== null && !in_array($code, $allowed, true)) {
                $reasons[] = __(':team mag alleen door :teams worden gefloten.', [
                    'team' => DutyRules::readable($targetCode),
                    'teams' => DutyRules::enumerate($allowed),
                ]);
            }
        }

        // Used to be Str::startsWith($code, 'C'); team codes start with the
        // category letter (JC1, MC1), so that rule never fired.
        if ($duty === DutyType::Scorer && $this->isCYouth($code)) {
            $heavy = $rules->scorerHeavyTeams();
            if ($heavy !== [] && in_array($targetCode, $heavy, true)) {
                $reasons[] = __('C-jeugd mag niet tellen bij :teams.', ['teams' => DutyRules::enumerate($heavy)]);
            }
            if ($start >= $rules->scorerCutoffMinutes()) {
                $reasons[] = __(self::LATE_C_YOUTH, ['time' => $rules->scorerCutoff()]);
            }
        }

        if ($duty === DutyType::HallDuty && !$rules->mayDoHallDuty($code)) {
            $reasons[] = __('Alleen senioren en A-jeugd mogen zaaldienst doen.');
        }

        return array_values(array_unique($reasons));
    }

    /** Lower is better: 0 plays in the same venue that day, 3 is not around at all. */
    public function presenceScore(Team $team, array $slot, Collection $matches, Collection $trainings): int
    {
        $date = $slot['date'];
        $own = $this->ownMatches($team, $date, $matches);
        if ($own->contains(fn (FixtureMatch $match): bool => $match->venue_id === $slot['venue_id'])) {
            return 0;
        }
        if ($trainings->where('team_id', $team->id)->where('weekday', Weekday::name($date))->contains('venue_id', $slot['venue_id'])) {
            return 1;
        }

        return $own->isNotEmpty() ? 2 : 3;
    }

    /** Preferred order of scorers: B/C youth first, seniors last. */
    public function scorerCategory(Team $team): int
    {
        $code = $this->code($team);

        return match (true) {
            $this->isBCYouth($code) => 0,
            $this->isLowerWomen($team, $this->rules()) => 1,
            $this->isAYouth($code) => 2,
            preg_match('/^(HS|DS)/', $code) === 1 => 3,
            default => 4,
        };
    }

    public function playsItself(Team $team, ?FixtureMatch $match): bool
    {
        return $match !== null && $this->isTeam($team, $match);
    }

    /**
     * The rules of the current club. They are read once per club because the
     * planner asks for them for every team on every slot.
     */
    public function rules(): DutyRules
    {
        return $this->rulesCache[app(CurrentClub::class)->id()] ??= new DutyRules(PlannerSetting::current()->duty_rules);
    }

    /**
     * The matches the team itself plays that day. Every time slot of a
     * playing day asks the same question, so the answer is cached per team
     * and day.
     *
     * @return Collection<int, FixtureMatch>
     */
    private function ownMatches(Team $team, string $date, Collection $matches): Collection
    {
        return $this->ownMatchesCache[$team->id.'|'.$date] ??= $matches
            ->filter(fn (FixtureMatch $match): bool => $match->date?->toDateString() === $date && $this->isTeam($team, $match))
            ->values();
    }

    private function isTeam(Team $team, FixtureMatch $match): bool
    {
        return $this->isTeamOnSide($team, (string) $match->home_team_name, $match->home_team_id)
            || $this->isTeamOnSide($team, (string) $match->away_team_name, $match->away_team_id);
    }

    /**
     * A recognisable club name takes precedence over an old, wrong team id.
     * Pool feeds of HS 7 also contain matches of HS 8 and used to link them
     * to HS 7. Without this check such an old row keeps counting as an away
     * match of HS 7.
     */
    private function isTeamOnSide(Team $team, string $name, ?int $teamId): bool
    {
        if ($this->matcher()->isOwnTeam($name)) {
            return $this->matcher()->isOwnTeamWithCode($name, $team->code);
        }

        return $team->id === $teamId;
    }

    /** Pure function asked for every team on every slot, so it is cached. */
    private function code(Team $team): string
    {
        return $this->codeCache[$team->id.'|'.$team->code] ??= TeamCode::key($team->code);
    }

    /**
     * Only a name of our own club yields a team code. Opponents often carry
     * the same code -- "D.V.C. Rebelle MC 1" contains "MC 1" -- and the code
     * must come after the club name ("Rebelle HS 2 - AETOS HS 3").
     */
    private function teamCodeFromName(string $name): string
    {
        return $this->nameCache[app(CurrentClub::class)->id().'|'.$name] ??= (string) $this->matcher()->ownTeamCode($name);
    }

    private function matcher(): ClubTeamMatcher
    {
        return ClubTeamMatcher::forCurrentClub();
    }

    private function isBCYouth(string $code): bool
    {
        return preg_match('/^[JM][BC]\d+$/', $code) === 1;
    }

    private function isAYouth(string $code): bool
    {
        return preg_match('/^[JM]A\d+$/', $code) === 1;
    }

    private function isCYouth(string $code): bool
    {
        return preg_match('/^[JM]C\d+$/', $code) === 1;
    }

    private function isLowerWomen(Team $team, DutyRules $rules): bool
    {
        return $rules->isLowerClass($team->name) && Str::startsWith($this->code($team), 'DS');
    }

    private function isYouthMatch(?FixtureMatch $match): bool
    {
        return $match && preg_match('/\b[JMN][A-C]\s*\d+\b/i', $match->home_team_name.' '.$match->away_team_name) === 1;
    }

    private function aYouthMayReferee(?FixtureMatch $match, DutyRules $rules): bool
    {
        return $this->isYouthMatch($match)
            || ($match && $rules->isLowerClass($match->pool.' '.$match->home_team_name));
    }

    private function minutes(?string $time): int
    {
        $key = (string) $time;
        if (array_key_exists($key, $this->minutesCache)) {
            return $this->minutesCache[$key];
        }

        [$hour, $minute] = array_pad(array_map('intval', explode(':', substr($key, 0, 5))), 2, 0);

        return $this->minutesCache[$key] = $hour * 60 + $minute;
    }

    private function overlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $startB < $endA;
    }
}
