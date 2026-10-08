<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\PlannerPlugin;
use App\Planner\Services\ExportService;
use App\Planner\Support\NodePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DayScheduleExportTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        $this->club = $this->useClub($this->createClub());
        $this->club->update(['name' => 'VV Demo']);
    }

    protected function tearDown(): void
    {
        PlannerPlugin::scheduleFooterUsing(null);

        parent::tearDown();
    }

    public function test_the_day_schedule_is_available_as_pdf_and_jpg(): void
    {
        $this->requiresChrome();
        $version = $this->planning();
        $service = app(ExportService::class);

        $pdf = $this->rendered(fn () => $service->daySchedulePdf($version, '2026-09-12'));
        $jpg = $this->rendered(fn () => $service->dayScheduleJpg($version, '2026-09-12'));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringStartsWith("\xFF\xD8\xFF", $jpg);
    }

    public function test_the_header_shows_the_venue_the_date_and_the_club_tagline(): void
    {
        $version = $this->planning();
        PlannerSetting::current()->update(['schedule_tagline' => 'Samen sterker']);

        $html = app(ExportService::class)->dayScheduleHtml($version, '2026-09-12');

        $this->assertStringContainsString('SPORTHAL DE LAAR', $html);
        $this->assertStringContainsString('12-09-2026', $html);
        $this->assertStringContainsString('<p class="header__tagline">Samen sterker</p>', $html);
        $this->assertStringContainsString('GRAAG MELDEN BIJ DE ZAALWACHT!', $html);
    }

    public function test_without_a_tagline_the_tagline_line_is_omitted(): void
    {
        $html = app(ExportService::class)->dayScheduleHtml($this->planning(), '2026-09-12');

        $this->assertStringNotContainsString('class="header__tagline"', $html);
    }

    public function test_the_day_schedule_carries_the_club_name_and_colour_and_never_aetos(): void
    {
        PlannerSetting::current()->update(['primary_color' => '#ab1234']);

        $html = app(ExportService::class)->dayScheduleHtml($this->planning(), '2026-09-12');

        $this->assertStringContainsString('--planner-primary: #ab1234;', $html);
        $this->assertStringContainsString('<div class="header__club-name">VV Demo</div>', $html);
        $this->assertStringNotContainsStringIgnoringCase('aetos', $html);
    }

    public function test_an_invalid_colour_falls_back_to_the_default(): void
    {
        PlannerSetting::current()->update(['primary_color' => 'red;}body{display:none']);

        $html = app(ExportService::class)->dayScheduleHtml($this->planning(), '2026-09-12');

        $this->assertStringContainsString('--planner-primary: #0B7F8A;', $html);
        $this->assertStringNotContainsString('display:none', $html);
    }

    public function test_the_club_logo_from_the_settings_replaces_the_club_name(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('planner/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        PlannerSetting::current()->update(['logo_path' => 'planner/logo.svg']);

        $html = app(ExportService::class)->dayScheduleHtml($this->planning(), '2026-09-12');

        $this->assertSame(2, substr_count($html, 'src="data:image/svg+xml;base64,'));
        $this->assertStringNotContainsString('<div class="header__club-name">', $html);
    }

    public function test_a_registered_schedule_footer_appears_in_the_output(): void
    {
        $version = $this->planning();
        $service = app(ExportService::class);

        $this->assertStringNotContainsString('class="schedule-footer"', $service->dayScheduleHtml($version, '2026-09-12'));

        PlannerPlugin::scheduleFooterUsing(fn () => new HtmlString('<div class="sponsors">Onze sponsors</div>'));

        $html = $service->dayScheduleHtml($version, '2026-09-12');
        $this->assertStringContainsString('class="schedule-footer"', $html);
        $this->assertStringContainsString('<div class="sponsors">Onze sponsors</div>', $html);
    }

    public function test_a_footer_text_for_the_export_takes_precedence_over_the_settings(): void
    {
        $version = $this->planning();
        PlannerSetting::current()->update(['schedule_footer' => 'UIT DE INSTELLINGEN']);
        $service = app(ExportService::class);

        $this->assertStringContainsString('UIT DE INSTELLINGEN', $service->dayScheduleHtml($version, '2026-09-12'));
        $html = $service->dayScheduleHtml($version, '2026-09-12', footerText: 'CHECK JE KLEEDKAMER');
        $this->assertStringContainsString('CHECK JE KLEEDKAMER', $html);
        $this->assertStringNotContainsString('UIT DE INSTELLINGEN', $html);
    }

    public function test_locker_rooms_only_show_for_venues_that_assign_them(): void
    {
        $version = $this->planning();
        $service = app(ExportService::class);

        $this->assertStringNotContainsString('class="card__locker-rooms"', $service->dayScheduleHtml($version, '2026-09-12'));

        Venue::query()->update(['show_locker_rooms_on_schedule' => true]);

        $this->assertStringContainsString('class="card__locker-rooms"', $service->dayScheduleHtml($version, '2026-09-12'));
    }

    public function test_locker_rooms_need_the_locker_rooms_module(): void
    {
        $this->useClub($this->createClub(['duties']));
        $version = $this->planning();
        Venue::query()->update(['show_locker_rooms_on_schedule' => true]);

        $html = app(ExportService::class)->dayScheduleHtml($version, '2026-09-12');

        $this->assertStringNotContainsString('class="card__locker-rooms"', $html);
    }

    public function test_duties_only_show_with_the_duties_module(): void
    {
        $version = $this->planning();
        HallDuty::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-12', 'start_time' => '09:00', 'end_time' => '13:00',
            'team_id' => Team::factory()->create(['code' => 'HS 2'])->id, 'venue_id' => Venue::query()->first()->id,
        ]);
        $service = app(ExportService::class);

        $html = $service->dayScheduleHtml($version, '2026-09-12');
        $this->assertStringContainsString('class="card__duties"', $html);
        $this->assertStringContainsString('class="hall-duty"', $html);
        $this->assertStringContainsString('HS 2', $html);

        $this->useClub($this->setClubFeatures($this->club, []));
        $html = $service->dayScheduleHtml($version, '2026-09-12');
        $this->assertStringNotContainsString('class="card__duties"', $html);
        $this->assertStringNotContainsString('class="hall-duty"', $html);
        $this->assertStringNotContainsString('class="column-header column-header--hall-duty"', $html);
    }

    public function test_the_day_schedule_stays_readable_with_many_courts_and_times(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();

        // Twelve courts and sixteen start times: the computed card sizes used
        // to turn negative here and GD drew an empty sheet.
        $courts = collect(range(1, 12))->map(fn (int $number) => Court::factory()->create([
            'venue_id' => $venue->id, 'name' => (string) $number, 'sort_order' => $number,
        ]));
        foreach (range(0, 15) as $index) {
            FixtureMatch::factory()->create([
                'version_id' => $version->id, 'date' => '2026-09-19',
                'time' => sprintf('%02d:00', 8 + $index), 'venue_id' => $venue->id,
                'court_id' => $courts[$index % 12]->id, 'match_code' => "MANY{$index}",
            ]);
        }

        $html = app(ExportService::class)->dayScheduleHtml($version, '2026-09-19', $venue->id);
        $this->assertStringContainsString('grid-template-columns: repeat(12,', $html);
        $this->assertStringContainsString('width: 3471px;', $html, 'The sheet should grow with the number of courts');

        $this->requiresChrome();
        $size = getimagesizefromstring($this->rendered(fn () => app(ExportService::class)->dayScheduleJpg($version, '2026-09-19', $venue->id)));
        $this->assertNotFalse($size, 'The export should produce a valid image');
        // The format is fixed; the layout below grows with the match day and
        // is then scaled into the frame.
        $this->assertSame([1920, 1080], [$size[0], $size[1]]);
    }

    /** Rendering launches Chrome through node; without node there is nothing to test. */
    private function requiresChrome(): void
    {
        if ((config('browsershot.node_binary') ?: NodePaths::binary('node')) === null) {
            $this->markTestSkipped('Node/Chrome is not available on this machine.');
        }
    }

    /**
     * Runs a real Chrome render. A machine where puppeteer's Chrome has not
     * been downloaded (npx puppeteer browsers install chrome) skips instead of
     * failing: that is an environment matter, not a layout error.
     */
    private function rendered(callable $render): string
    {
        try {
            return $render();
        } catch (RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'Could not find Chrome')) {
                $this->markTestSkipped('Puppeteer\'s Chrome is not installed on this machine.');
            }

            throw $exception;
        }
    }

    private function planning(): ScheduleVersion
    {
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create(['name' => 'Publicatie']);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'ABC1',
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
            'home_team_name' => 'VV Demo DS 1', 'away_team_name' => 'Orion DS 2',
        ]);

        return $version;
    }
}
