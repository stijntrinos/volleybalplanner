<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\ConflictEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The planner drew trainings and hall activities, but did not check them:
 * you got a green light while the hall was occupied.
 */
class ConflictEngineOccupancyTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS'], 'buffer_minutes' => 0]);
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
    }

    public function test_a_training_in_the_same_venue_gives_a_warning(): void
    {
        $team = Team::factory()->create(['code' => 'HS 2']);
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => $this->venue->id,
            'weekday' => 'zaterdag', 'start_time' => '10:00', 'end_time' => '12:00', 'court' => 1,
        ]);

        // 2026-09-19 is a Saturday.
        $findings = $this->check('2026-09-19', '11:00');

        $training = collect($findings)->firstWhere('type', ConflictEngine::TYPE_TRAINING);
        $this->assertNotNull($training, 'An overlapping training should be reported');
        $this->assertSame(ConflictEngine::LEVEL_WARNING, $training['level']);
        $this->assertStringContainsString('HS 2', $training['message']);
    }

    public function test_a_training_on_another_weekday_says_nothing(): void
    {
        Training::factory()->create([
            'venue_id' => $this->venue->id, 'weekday' => 'maandag', 'start_time' => '10:00', 'end_time' => '12:00',
        ]);

        $this->assertNull(collect($this->check('2026-09-19', '11:00'))->firstWhere('type', ConflictEngine::TYPE_TRAINING));
    }

    public function test_without_the_trainings_module_trainings_are_not_checked(): void
    {
        $this->useClub($this->createClub(array_values(array_diff(PlannerFeature::allValues(), ['trainings']))));
        PlannerSetting::current()->update(['buffer_minutes' => 0]);
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id]);
        Training::factory()->create([
            'venue_id' => $this->venue->id, 'weekday' => 'zaterdag', 'start_time' => '10:00', 'end_time' => '12:00',
        ]);

        $this->assertNull(collect($this->check('2026-09-19', '11:00'))->firstWhere('type', ConflictEngine::TYPE_TRAINING));
    }

    public function test_a_hall_activity_on_the_same_court_gives_a_warning(): void
    {
        Activity::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-19',
            'start_time' => '10:30', 'end_time' => '13:00', 'title' => 'Sinterklaasfeest',
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);

        $finding = collect($this->check('2026-09-19', '11:00'))->firstWhere('type', ConflictEngine::TYPE_ACTIVITY);

        $this->assertNotNull($finding);
        $this->assertSame(ConflictEngine::LEVEL_WARNING, $finding['level']);
        $this->assertStringContainsString('Sinterklaasfeest', $finding['message']);
    }

    public function test_too_little_changeover_time_falls_within_the_configured_buffer(): void
    {
        PlannerSetting::current()->update(['buffer_minutes' => 30]);
        $this->match('EARLIER', '2026-09-19', '09:00');

        // 09:00 + 105 min = 10:45, so 15 minutes changeover with a start at 11:00.
        $findings = $this->check('2026-09-19', '11:00');

        $buffer = collect($findings)->firstWhere('type', ConflictEngine::TYPE_BUFFER);
        $this->assertNotNull($buffer, 'Too tight a changeover should be reported');
        $this->assertSame(ConflictEngine::LEVEL_WARNING, $buffer['level']);
        $this->assertNull(collect($findings)->firstWhere('type', ConflictEngine::TYPE_COURT_OVERLAP));
    }

    public function test_ample_changeover_time_gives_no_finding(): void
    {
        PlannerSetting::current()->update(['buffer_minutes' => 15]);
        $this->match('EARLIER', '2026-09-19', '09:00');

        $this->assertNull(collect($this->check('2026-09-19', '12:00'))->firstWhere('type', ConflictEngine::TYPE_BUFFER));
    }

    /** @return list<array<string, mixed>> */
    private function check(string $date, string $time): array
    {
        $match = $this->match('CURRENT', $date, $time, ['home_team_name' => 'AETOS HS 1', 'away_team_name' => 'Dynamo HS 1']);

        return app(ConflictEngine::class)->checkPlacement($match, [
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id, 'second_court_id' => null,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function match(string $code, string $date, string $time, array $extra = []): FixtureMatch
    {
        return FixtureMatch::factory()->create($extra + [
            'version_id' => $this->version->id, 'match_code' => $code,
            'date' => $date, 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
        ]);
    }
}
