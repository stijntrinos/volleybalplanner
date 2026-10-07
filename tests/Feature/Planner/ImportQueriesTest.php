<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

/**
 * The import went back to the database per row for the venues, the teams and
 * the court. For a season file of a few hundred matches that adds up to
 * thousands of queries in one transaction.
 */
class ImportQueriesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    public function test_update_does_not_read_venues_and_teams_per_row(): void
    {
        [$version, $file] = $this->planning(12);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ImportService::class)->updateVersion($file, $version);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $venues = $queries->filter(fn (string $q): bool => str_contains($q, 'from "planner_venues"'))->count();
        $teams = $queries->filter(fn (string $q): bool => str_contains($q, 'from "planner_teams"'))->count();

        $this->assertLessThanOrEqual(6, $venues, "The venue table was read {$venues} times for 12 rows.");
        $this->assertLessThanOrEqual(6, $teams, "The team table was read {$teams} times for 12 rows.");
    }

    public function test_more_rows_do_not_cost_more_table_reads(): void
    {
        $small = $this->tableReads(4);
        $large = $this->tableReads(16);

        $this->assertSame($small, $large, "4 rows cost {$small} table reads, 16 rows {$large}.");
    }

    public function test_update_keeps_one_venue_and_court(): void
    {
        [$version, $file] = $this->planning(6);

        app(ImportService::class)->updateVersion($file, $version);

        $this->assertSame(6, $version->matches()->count());
        $this->assertSame(1, Venue::query()->count());
        $this->assertSame(1, Court::query()->count());
    }

    private function tableReads(int $rows): int
    {
        [$version, $file] = $this->planning($rows);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ImportService::class)->updateVersion($file, $version);
        $count = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $q): bool => str_contains($q, 'from "planner_venues"')
                || str_contains($q, 'from "planner_teams"'))
            ->count();
        DB::disableQueryLog();

        return $count;
    }

    /** @return array{0: ScheduleVersion, 1: string} */
    private function planning(int $count): array
    {
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create(['name' => 'De Helster', 'venue_code' => 'HEL']);
        Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);

        $rows = [];
        foreach (range(1, $count) as $number) {
            $rows[] = [now()->addWeeks($number)->format('d-m-Y'), '19:00', 'AETOS DS 1', "Orion DS {$number}", 'De Helster', '1', 'Oost', 'D2A', "WK{$number}", 'HEL', 'Bemmel'];
            FixtureMatch::factory()->create([
                'version_id' => $version->id, 'match_code' => "WK{$number}",
                'date' => now()->addWeeks($number)->toDateString(), 'time' => '20:00',
                'home_team_name' => 'AETOS DS 1', 'away_team_name' => "Orion DS {$number}",
                'match_type' => MatchType::Home, 'venue_id' => $venue->id,
            ]);
        }

        return [$version, $this->planningCsv($rows)];
    }
}
