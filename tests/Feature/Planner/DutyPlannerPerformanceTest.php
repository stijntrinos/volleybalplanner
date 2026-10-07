<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\DutyPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * A generation run as the planning committee does it: a version holding a
 * whole season, planned two months at a time.
 *
 * This run took 120 seconds when every rule check scanned the whole season
 * list, and under a second once matches and trainings were indexed per day.
 * The limit is well above that: it should not complain about a slow machine,
 * only about that scan coming back.
 */
class DutyPlannerPerformanceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_planning_two_months_stays_workable(): void
    {
        [$version, $from, $to] = $this->season();

        $start = microtime(true);
        $draft = app(DutyPlanner::class)->generate($version, DutyPlanner::MODE_FILL, $from, $to);
        $seconds = microtime(true) - $start;

        $this->assertSame(850, FixtureMatch::query()->count());
        $this->assertCount(200, $draft['matches']);
        $this->assertCount(100, $draft['hall_duties']);
        $this->assertLessThan(30, $seconds, sprintf('Generating took %.1f seconds.', $seconds));
    }

    /** @return array{0: ScheduleVersion, 1: string, 2: string} */
    private function season(): array
    {
        $club = $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $version = ScheduleVersion::factory()->create();
        $venueA = Venue::factory()->create(['name' => 'Middachtensingel']);
        $venueB = Venue::factory()->create(['name' => 'Rijkerswoerd']);

        $codes = [];
        foreach (['HS', 'DS'] as $category) {
            foreach (range(1, 8) as $number) {
                $codes[] = $category.' '.$number;
            }
        }
        foreach (['JA', 'MA', 'JB', 'MB', 'JC', 'MC'] as $category) {
            foreach (range(1, 4) as $number) {
                $codes[] = $category.' '.$number;
            }
        }
        foreach ($codes as $code) {
            Team::factory()->create(['code' => $code, 'name' => 'AETOS '.$code, 'include_in_duties' => true]);
        }

        $rows = [];
        // A fixed date would end up in the past, and a played day is not
        // filled anymore. The series therefore starts next Saturday.
        $saturday = CarbonImmutable::now()->next('Saturday');
        $times = ['09:00', '10:45', '12:30', '14:15', '16:00'];
        $now = now()->toDateTimeString();
        $base = ['club_id' => $club->id, 'version_id' => $version->id, 'duration_minutes' => 105, 'is_sitting_volleyball' => false, 'created_at' => $now, 'updated_at' => $now];
        for ($week = 0; $week < 34; $week++) {
            $date = $saturday->addWeeks($week)->toDateString();
            foreach ($times as $timeIndex => $time) {
                foreach ([$venueA, $venueB] as $venueIndex => $venue) {
                    foreach ([1, 2] as $court) {
                        $rows[] = $base + [
                            'date' => $date, 'time' => $time, 'venue_id' => $venue->id,
                            'home_team_name' => 'AETOS '.$codes[($week + $timeIndex + $venueIndex + $court) % count($codes)],
                            'away_team_name' => 'Bezoeker '.$court.' HS 1',
                            'match_code' => sprintf('T%03d-%d%d%d', $week, $timeIndex, $venueIndex, $court),
                            'match_type' => MatchType::Home->value,
                        ];
                    }
                }
                $rows[] = $base + [
                    'date' => $date, 'time' => $time, 'venue_id' => null,
                    'home_team_name' => 'Tegenstander HS 1',
                    'away_team_name' => 'AETOS '.$codes[($week + $timeIndex + 7) % count($codes)],
                    'match_code' => sprintf('U%03d-%d', $week, $timeIndex),
                    'match_type' => MatchType::Away->value,
                ];
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('planner_matches')->insert($chunk);
        }

        // Ten playing days: 200 home matches and 100 hall duties.
        return [$version, $saturday->toDateString(), $saturday->addWeeks(9)->toDateString()];
    }
}
