<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\DutyImportService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

class DutyImportTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    private const HEADER = 'Seizoen;Datum;Begintijd;Eindtijd;Dienstsoort;Team;Zaalcode;Sporthal;Veld;Wedstrijdcode;Thuisteam;Uitteam';

    private Club $club;

    private ScheduleVersion $version;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->club = $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $this->version = ScheduleVersion::factory()->create();
        $this->venue = Venue::factory()->create(['venue_code' => 'AHMVA', 'name' => 'Valkenhuizen']);
        foreach (['HS 7', 'MB 5', 'JA 1', 'DS 10'] as $code) {
            Team::factory()->create(['code' => $code, 'name' => "AETOS {$code}"]);
        }
        Team::factory()->create(['code' => 'DS 1', 'name' => 'AETOS Plauti DS 1']);
    }

    public function test_referee_and_scorer_duty_become_fixed_assignments_on_the_match(): void
    {
        $match = $this->match('BM1A-11');
        $file = $this->csv([
            $this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11'),
            $this->row('Teldienst', 'AETOS MB 5', 'BM1A-11'),
        ]);

        $report = $this->service()->import($file, $this->version);

        $match->refresh();
        $this->assertSame('HS 7', $match->referee);
        $this->assertFalse($match->referee_auto);
        $this->assertSame('MB 5', $match->scorer);
        $this->assertFalse($match->scorer_auto);
        $this->assertSame(['referee' => 1, 'scorer' => 1, 'hall_duty' => 0], $report['assigned']);
    }

    public function test_an_existing_assignment_stays_unless_overwrite_is_on(): void
    {
        $match = $this->match('BM1A-11', ['referee' => 'JA 1', 'referee_auto' => true]);
        $file = $this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]);

        $report = $this->service()->import($file, $this->version);
        $this->assertSame('JA 1', $match->fresh()->referee);
        $this->assertCount(1, $report['skipped']);
        $this->assertStringContainsString('JA 1', $report['skipped'][0]['reason']);

        $this->service()->import($file, $this->version, overwrite: true);
        $this->assertSame('HS 7', $match->fresh()->referee);
        $this->assertFalse($match->fresh()->referee_auto);
    }

    public function test_a_hall_duty_becomes_its_own_row_with_team_venue_and_times(): void
    {
        $this->match('BM1A-11');
        $file = $this->csv([$this->row('Zaaldienst', 'AETOS JA 1', 'BM1A-11', '2026-09-05', '19:30', '21:30')]);

        $report = $this->service()->import($file, $this->version);

        $duty = HallDuty::query()->sole();
        $this->assertSame('2026-09-05', $duty->date->toDateString());
        $this->assertSame('19:30', substr($duty->start_time, 0, 5));
        $this->assertSame('21:30', substr($duty->end_time, 0, 5));
        $this->assertSame($this->venue->id, $duty->venue_id);
        $this->assertSame('JA 1', $duty->team->code);
        $this->assertFalse($duty->team_auto);
        $this->assertSame($this->version->id, $duty->version_id);
        $this->assertSame(1, $report['assigned']['hall_duty']);
    }

    public function test_importing_the_same_hall_duty_again_gives_no_duplicate_row(): void
    {
        $this->match('BM1A-11');
        $file = $this->csv([$this->row('Zaaldienst', 'AETOS JA 1', 'BM1A-11', '2026-09-05', '19:30', '21:30')]);

        $this->service()->import($file, $this->version);
        $this->service()->import($file, $this->version);

        $this->assertSame(1, HallDuty::query()->count());
    }

    public function test_a_hall_duty_with_another_team_is_only_replaced_with_overwrite(): void
    {
        $this->match('BM1A-11');
        $this->service()->import($this->csv([
            $this->row('Zaaldienst', 'AETOS JA 1', 'BM1A-11', '2026-09-05', '19:30', '21:30'),
        ]), $this->version);
        $file = $this->csv([$this->row('Zaaldienst', 'AETOS DS 10', 'BM1A-11', '2026-09-05', '19:30', '21:30')]);

        $report = $this->service()->import($file, $this->version);
        $this->assertSame('JA 1', HallDuty::query()->sole()->team->code);
        $this->assertCount(1, $report['skipped']);

        $this->service()->import($file, $this->version, overwrite: true);
        $this->assertSame('DS 10', HallDuty::query()->sole()->team->code);
        $this->assertSame(1, HallDuty::query()->count());
    }

    public function test_the_team_name_from_the_file_links_by_team_code_also_with_sponsor_name(): void
    {
        $match = $this->match('BM1A-11');

        $this->service()->import($this->csv([$this->row('Fluitdienst', 'AETOS DS 1', 'BM1A-11')]), $this->version);

        $this->assertSame('DS 1', $match->fresh()->referee);
    }

    public function test_ds_1_is_not_confused_with_ds_10(): void
    {
        $match = $this->match('BM1A-11');

        $this->service()->import($this->csv([$this->row('Teldienst', 'AETOS DS 10', 'BM1A-11')]), $this->version);

        $this->assertSame('DS 10', $match->fresh()->scorer);
    }

    public function test_excel_numbers_for_date_and_time_are_read(): void
    {
        $this->match('BM1A-11');
        // 46268 = 2026-09-03, 0.8125 = 19:30, 0.8958333 = 21:30
        $file = $this->csv([$this->row('Zaaldienst', 'AETOS JA 1', 'BM1A-11', '46268', '0.8125', '0.89583333333333004')]);

        $this->service()->import($file, $this->version);

        $duty = HallDuty::query()->sole();
        $this->assertSame('2026-09-03', $duty->date->toDateString());
        $this->assertSame('19:30', substr($duty->start_time, 0, 5));
        $this->assertSame('21:30', substr($duty->end_time, 0, 5));
    }

    public function test_unknown_match_or_team_goes_into_the_report_and_the_rest_continues(): void
    {
        $match = $this->match('BM1A-11');
        $file = $this->csv([
            $this->row('Fluitdienst', 'AETOS HS 7', 'XX9Z-99'),
            $this->row('Teldienst', 'AETOS MC 9', 'BM1A-11'),
            $this->row('Teldienst', 'AETOS MB 5', 'BM1A-11'),
        ]);

        $report = $this->service()->import($file, $this->version);

        $this->assertSame('MB 5', $match->fresh()->scorer);
        $this->assertCount(2, $report['unmatched']);
        $this->assertStringContainsString('XX9Z-99', $report['unmatched'][0]['reason']);
        $this->assertStringContainsString('AETOS MC 9', $report['unmatched'][1]['reason']);
    }

    public function test_a_match_from_another_version_is_not_touched(): void
    {
        $other = ScheduleVersion::factory()->create();
        $foreign = $this->match('BM1A-11', ['version_id' => $other->id]);

        $report = $this->service()->import($this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]), $this->version);

        $this->assertNull($foreign->fresh()->referee);
        $this->assertCount(1, $report['unmatched']);
    }

    public function test_volleystar_duties_are_only_counted(): void
    {
        $report = $this->service()->import($this->csv([$this->row('Volleystar-dienst', 'AETOS HS 7', '', '2026-10-03', '08:00', '12:30')]), $this->version);

        $this->assertSame(1, $report['volleystars']);
        $this->assertSame(['referee' => 0, 'scorer' => 0, 'hall_duty' => 0], $report['assigned']);
    }

    public function test_analyzing_changes_nothing_but_does_report(): void
    {
        $match = $this->match('BM1A-11');
        $file = $this->csv([
            $this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11'),
            $this->row('Zaaldienst', 'AETOS JA 1', 'BM1A-11', '2026-09-05', '19:30', '21:30'),
        ]);

        $report = $this->service()->analyze($file, $this->version);

        $this->assertNull($match->fresh()->referee);
        $this->assertSame(0, HallDuty::query()->count());
        $this->assertSame(['referee' => 1, 'scorer' => 0, 'hall_duty' => 1], $report['assigned']);
    }

    public function test_missing_columns_give_a_clear_error(): void
    {
        $file = $this->rawCsv("Datum;Team\n2026-09-05;AETOS HS 7");

        $this->expectExceptionMessage('Verplichte kolommen ontbreken');
        $this->service()->analyze($file, $this->version);
    }

    public function test_without_the_duties_module_the_import_refuses(): void
    {
        $this->setClubFeatures($this->club, []);

        $this->expectException(RuntimeException::class);
        $this->service()->analyze($this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]), $this->version);
    }

    public function test_the_command_reports_without_apply_and_writes_with_apply(): void
    {
        $match = $this->match('BM1A-11');
        $file = $this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]);

        $this->artisan("planner:import-duties \"{$file}\" --club={$this->club->slug} --schedule-version={$this->version->id}")
            ->assertSuccessful();
        $this->assertNull($match->fresh()->referee);

        $this->artisan("planner:import-duties \"{$file}\" --club={$this->club->slug} --schedule-version={$this->version->id} --apply --no-interaction")
            ->assertSuccessful();
        $this->assertSame('HS 7', $match->fresh()->referee);
    }

    public function test_the_command_finds_a_file_by_name_in_the_project_directory(): void
    {
        $match = $this->match('BM1A-11');
        $name = 'diensten-test-'.uniqid().'.csv';
        copy($this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]), base_path($name));
        $workdir = getcwd();

        // On the server the working directory is rarely the project directory.
        chdir(sys_get_temp_dir());
        try {
            $this->artisan("planner:import-duties \"{$name}\" --club={$this->club->slug} --schedule-version={$this->version->id} --apply --no-interaction")
                ->assertSuccessful();
        } finally {
            chdir($workdir);
            unlink(base_path($name));
        }

        $this->assertSame('HS 7', $match->fresh()->referee);
    }

    public function test_the_command_refuses_an_unknown_version(): void
    {
        $file = $this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]);

        $this->artisan("planner:import-duties \"{$file}\" --club={$this->club->slug} --schedule-version=999 --apply --no-interaction")
            ->assertFailed();
    }

    public function test_the_command_does_not_find_a_version_of_another_club(): void
    {
        $other = $this->createClub();
        $foreignVersion = app(CurrentClub::class)->run($other, fn (): ScheduleVersion => ScheduleVersion::factory()->create());
        $file = $this->csv([$this->row('Fluitdienst', 'AETOS HS 7', 'BM1A-11')]);

        $this->artisan("planner:import-duties \"{$file}\" --club={$this->club->slug} --schedule-version={$foreignVersion->id} --apply --no-interaction")
            ->assertFailed();
    }

    private function service(): DutyImportService
    {
        return app(DutyImportService::class);
    }

    /** @param  array<string, mixed>  $extra */
    private function match(string $code, array $extra = []): FixtureMatch
    {
        return FixtureMatch::factory()->create(array_merge([
            'version_id' => $this->version->id, 'match_code' => $code,
            'date' => '2026-09-05', 'time' => '19:30', 'venue_id' => $this->venue->id,
            'home_team_name' => 'AETOS MA 1', 'away_team_name' => 'Tweestroom MA 1',
        ], $extra));
    }

    private function row(string $kind, string $team, string $code, string $date = '2026-09-05', string $start = '19:30', string $end = '21:30'): string
    {
        return implode(';', ['2026-2027', $date, $start, $end, $kind, $team, 'AHMVA', 'Valkenhuizen', '3', $code, 'AETOS MA 1', 'Tweestroom MA 1']);
    }

    /** @param  list<string>  $lines */
    private function csv(array $lines): string
    {
        return $this->rawCsv(implode("\n", [self::HEADER, ...$lines]));
    }
}
