<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\ExportService;
use App\Planner\Services\VenueOccupancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;
use ZipArchive;

class ExportServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_planning_excel_is_a_valid_xlsx_with_a_venue_sheet(): void
    {
        $version = $this->planning();
        $path = app(ExportService::class)->planningExcel($version);
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path));
        $this->assertStringContainsString('De Laar', $zip->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('ABC1', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_planning_excel_has_no_referee_columns_without_the_duties_module(): void
    {
        $this->useClub($this->createClub([]));
        $version = $this->planning();
        $path = app(ExportService::class)->planningExcel($version);
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('ABC1', $sheet);
        $this->assertStringNotContainsString('Scheidsrechter', $sheet);
        $zip->close();
        unlink($path);
    }

    public function test_planning_pdf_is_a_pdf_document(): void
    {
        $pdf = app(ExportService::class)->planningPdf($this->planning());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_duties_excel_has_all_match_days_on_one_sheet_with_the_duties_per_match(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create(['name' => 'Publicatie']);
        $ds1 = Team::factory()->create(['code' => 'DS1', 'name' => 'VV Demo DS 1']);
        $ds2 = Team::factory()->create(['code' => 'DS2', 'name' => 'VV Demo DS 2']);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'FTZ-1',
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 120,
            'venue_id' => $venue->id, 'court_id' => $court->id,
            'home_team_id' => $ds1->id, 'home_team_name' => $ds1->name, 'away_team_name' => 'Orion DS 3',
            'referee' => 'DS2', 'scorer' => 'DS1',
        ]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'FTZ-2',
            'date' => '2026-09-19', 'time' => '12:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
            'referee' => 'DS1', 'scorer' => 'DS2',
        ]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'OUTSIDE-PERIOD',
            'date' => '2026-10-12', 'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);
        HallDuty::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-12',
            'start_time' => '09:30', 'end_time' => '13:00', 'team_id' => $ds2->id, 'venue_id' => $venue->id,
        ]);

        $path = app(ExportService::class)->dutiesExcel($version, '2026-09-01', '2026-09-30');
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path));
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('FTZ wedstrijddagen', $workbook);
        $this->assertSame(1, substr_count($workbook, '<sheet '));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('FTZ-1', $sheet);
        $this->assertStringContainsString('FTZ-2', $sheet);
        $this->assertStringContainsString('Fluiten', $sheet);
        $this->assertStringContainsString('Tellen', $sheet);
        $this->assertStringContainsString('Zaalwacht', $sheet);
        $this->assertStringNotContainsString('Zaalwachtperiode', $sheet);
        $this->assertStringContainsString('DS2 (09:30–13:00)', $sheet);
        $this->assertStringNotContainsString('OUTSIDE-PERIOD', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
        $zip->close();
        unlink($path);
    }

    public function test_the_day_export_can_be_filtered_by_venue(): void
    {
        $firstVenue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $secondVenue = Venue::factory()->create(['name' => 'De Laar']);
        $firstCourt = Court::factory()->create(['venue_id' => $firstVenue->id, 'name' => '1']);
        $secondCourt = Court::factory()->create(['venue_id' => $secondVenue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'MID-1',
            'date' => '2026-09-12', 'venue_id' => $firstVenue->id, 'court_id' => $firstCourt->id,
        ]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'LAAR-1',
            'date' => '2026-09-12', 'venue_id' => $secondVenue->id, 'court_id' => $secondCourt->id,
        ]);

        $path = app(ExportService::class)->dayExcel($version, '2026-09-12', $firstVenue->id);
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('MID-1', $sheet);
        $this->assertStringNotContainsString('LAAR-1', $sheet);
        $zip->close();
        unlink($path);
    }

    public function test_the_conflicts_report_is_a_valid_xlsx(): void
    {
        $path = app(ExportService::class)->conflictsExcel($this->planning());
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($path));
        $this->assertStringContainsString('Conflicten', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
    }

    public function test_nevobo_changes_can_go_to_excel_and_pdf(): void
    {
        $changes = [[
            'code' => 'ABC1', 'date' => '2026-09-12', 'home_team' => 'VV Demo HS 1', 'away_team' => 'Orion HS 1',
            'fields' => [['field' => 'time', 'old' => '10:00', 'new' => '12:00', 'processed' => false]],
        ]];
        $service = app(ExportService::class);
        $excel = $service->nevoboChangesExcel($changes);
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($excel));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('ABC1', $sheet);
        $this->assertStringContainsString('Openstaand', $sheet);
        $zip->close();
        unlink($excel);
        $this->assertStringStartsWith('%PDF-', $service->nevoboChangesPdf($changes));
    }

    public function test_venue_occupancy_can_go_to_excel_and_pdf(): void
    {
        $overview = [
            'from' => '2026-09-01', 'to' => '2026-09-30', 'total' => 1, 'conflicts' => 1,
            'venues' => [[
                'venue_id' => 1, 'venue' => 'De Laar',
                'rows' => [[
                    'date' => '2026-09-25', 'day' => 'vr',
                    'available_from' => '18:30', 'available_until' => '22:00',
                    'matches' => 1, 'first' => '21:00', 'last' => '21:00', 'end_of_last' => '22:45',
                    'status' => VenueOccupancyService::STATUS_CONFLICT, 'note' => 'Zaal eerder dicht',
                    'details' => [[
                        'code' => 'ABC1', 'home' => 'VV Demo DS 1', 'away' => 'Orion DS 1',
                        'court' => '1', 'start' => '21:00', 'end' => '22:45', 'duration' => '105 min',
                    ]],
                ]],
            ]],
        ];
        $service = app(ExportService::class);
        $excel = $service->venueOccupancyExcel($overview, true);
        $zip = new ZipArchive;

        $this->assertTrue($zip->open($excel));
        $this->assertStringContainsString('De Laar', $zip->getFromName('xl/workbook.xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Zaal eerder dicht', $sheet, 'The note should be included.');
        $this->assertStringContainsString('ABC1', $sheet, 'With details the match line should be below it.');
        $zip->close();
        unlink($excel);

        $this->assertStringStartsWith('%PDF-', $service->venueOccupancyPdf($overview, true));
    }

    public function test_pdf_exports_use_the_club_colour(): void
    {
        $html = view('planner::exports.planning', [
            'version' => $this->planning(),
            'matches' => collect(),
            'showDuties' => true,
            'primaryColor' => '#123456',
        ])->render();

        $this->assertStringContainsString('background:#123456', $html);
        $this->assertStringNotContainsString('087fae', $html);
    }

    private function planning(): ScheduleVersion
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create(['name' => 'Publicatie']);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'ABC1',
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);

        return $version;
    }
}
