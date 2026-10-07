<?php

namespace Tests\Feature\Planner\Concerns;

use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\RuleEngine;
use Carbon\CarbonImmutable;

/**
 * Shared fixtures for the duty planner tests: a club whose teams are called
 * "AETOS ..." (unless told otherwise), one venue and one version.
 */
trait BuildsDutyFixtures
{
    protected Venue $venue;

    protected ScheduleVersion $version;

    /**
     * A Saturday in the future: the planner leaves played days alone, so a
     * fixed date would stop being filled once it has passed.
     */
    protected string $saturday;

    /**
     * @param  list<string>  $patterns
     * @param  list<string>|null  $features
     */
    protected function setUpDutyClub(array $patterns = ['AETOS'], ?array $features = null): void
    {
        $this->saturday = $this->saturdayInWeeks(2);
        $this->useClub($this->createClub($features));
        PlannerSetting::current()->update(['team_name_patterns' => $patterns]);
        $this->venue = Venue::factory()->create();
        $this->version = ScheduleVersion::factory()->create();
    }

    protected function saturdayInWeeks(int $weeks): string
    {
        return CarbonImmutable::now()->next('Saturday')->addWeeks($weeks)->toDateString();
    }

    protected function team(string $code, array $attributes = []): Team
    {
        return Team::factory()->create($attributes + ['code' => $code, 'include_in_duties' => true]);
    }

    protected function homeMatch(array $attributes = []): FixtureMatch
    {
        return FixtureMatch::factory()->create($attributes + [
            'version_id' => $this->version->id, 'match_type' => MatchType::Home,
            'date' => $this->saturday, 'time' => '10:00', 'venue_id' => $this->venue->id,
            'home_team_name' => 'AETOS HS 5', 'away_team_name' => 'Tegenstander',
            'referee' => null, 'scorer' => null,
        ]);
    }

    /** A slot for a home match of this team on 2026-09-12 at 10:00. */
    protected function slot(string $homeTeam): array
    {
        return [
            'date' => $this->saturday, 'start' => '10:00', 'end' => '11:45',
            'venue_id' => $this->venue->id, 'match' => $this->homeMatch(['home_team_name' => $homeTeam]),
        ];
    }

    /** @return list<string> */
    protected function violations(Team $team, DutyType $duty, array $slot, bool $withMatches = true): array
    {
        return app(RuleEngine::class)->violations(
            $team, $duty, $slot, $withMatches ? $this->version->matches()->get() : collect(), collect(),
        );
    }
}
