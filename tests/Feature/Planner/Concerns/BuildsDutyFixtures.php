<?php

namespace Tests\Feature\Planner\Concerns;

use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\Duties\RuleEngine;
use App\Planner\Services\Duties\TeamChoice;
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

    /** A home match of this team on the test Saturday at 10:00. */
    protected function matchOf(string $homeTeam, array $attributes = []): FixtureMatch
    {
        return $this->homeMatch($attributes + ['home_team_name' => $homeTeam]);
    }

    /** The context of the test version, as the planner builds it. */
    protected function context(): PlanningContext
    {
        return PlanningContext::forVersion($this->version);
    }

    /** The duty of a match in a fresh context. */
    protected function dutyOf(FixtureMatch $match, string $duty, ?PlanningContext $context = null): array
    {
        $context ??= $this->context();

        return $context->dutyForMatch($context->homeMatches()->firstWhere('id', $match->id), $duty);
    }

    /**
     * All reasons (hard, then soft with "Niet voorgesteld:") for this team on
     * this duty of a match.
     *
     * @return list<string>
     */
    protected function violations(Team $team, FixtureMatch $match, string $duty): array
    {
        $context = $this->context();

        return app(RuleEngine::class)->violations($context->teamWithCode($team->code) ?? $team, $this->dutyOf($match, $duty, $context), $context);
    }

    /** The team the generator proposes for this duty of a match. */
    protected function best(FixtureMatch $match, string $duty): ?string
    {
        $context = $this->context();

        return app(TeamChoice::class)->best($this->dutyOf($match, $duty, $context), $context)?->code;
    }

    /** @return list<array{team: Team, code: string, status: string, balance: PointsBalance, reasons: list<string>}> */
    protected function candidates(FixtureMatch $match, string $duty): array
    {
        $context = $this->context();

        return app(TeamChoice::class)->candidates($this->dutyOf($match, $duty, $context), $context);
    }

    /** @return array{team: Team, code: string, status: string, balance: PointsBalance, reasons: list<string>}|null */
    protected function candidate(FixtureMatch $match, string $duty, string $code): ?array
    {
        return collect($this->candidates($match, $duty))->firstWhere('code', $code);
    }
}
