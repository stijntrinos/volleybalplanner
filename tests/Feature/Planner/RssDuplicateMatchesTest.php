<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Services\RssPlanningImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Twice in a row the nightly sync doubled the whole planning because the match
 * code from the feed did not line up with the planning file: first the
 * district number, then the sequence number of a repeated fixture. These tests
 * cover the translation and the safety net that does not depend on the code.
 * (Parsing of cancelled matches is covered in NevoboRssServiceTest.)
 */
class RssDuplicateMatchesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);

        $team = Team::factory()->create(['code' => 'JB 1', 'name' => 'AET']);
        TeamSource::factory()->create([
            'team_id' => $team->id, 'name' => 'Competitie', 'is_active' => true,
            'url' => 'https://api.nevobo.nl/export/poule/regio-oost/JBHA1/programma.rss',
        ]);
    }

    public function test_the_second_fixture_is_updated_and_not_doubled(): void
    {
        // As the planning file writes it: the second fixture is "-2".
        $existing = $this->match('JBHA1-BA-2', '2026-11-07', '12:30');

        $this->feed('5000JBHA1 BA-1', 'zaterdag 7 november', '12:30');
        $result = $this->service()->sync($this->version);

        $this->assertSame(0, $result['added']);
        $this->assertSame(1, FixtureMatch::query()->count());
        $this->assertSame('JBHA1-BA-2', $existing->fresh()->match_code);
    }

    public function test_a_first_fixture_stays_a_match_of_its_own(): void
    {
        $this->match('JBHA1-BA', '2026-09-12', '10:45');

        $this->feed('5000JBHA1 BA-1', 'zaterdag 7 november', '12:30');
        $result = $this->service()->sync($this->version);

        // Another date, another fixture: that one should be added.
        $this->assertSame(1, $result['added']);
        $this->assertSame(2, FixtureMatch::query()->count());
    }

    /**
     * The safety net: even when the codes do not line up, no second row may
     * appear for a match that is already there. Two teams cannot play each
     * other twice at the same moment.
     */
    public function test_an_unknown_code_shape_does_not_double_the_match(): void
    {
        $existing = $this->match('JBHA1-BA-2', '2026-11-07', '12:30');

        $this->feed('XYZ-NIEUWE-OPMAAK-7', 'zaterdag 7 november', '12:30');
        $result = $this->service()->sync($this->version);

        $this->assertSame(0, $result['added']);
        $this->assertSame(1, FixtureMatch::query()->count());
        $this->assertSame('XYZ-NIEUWE-OPMAAK-7', $existing->fresh()->match_code);
    }

    /**
     * A cancelled match is no longer in the planning file but still in the
     * feed. Created by the nightly sync, it was "removed" at the next import
     * and came back the morning after deleting it.
     */
    public function test_the_sync_does_not_create_a_cancelled_match(): void
    {
        $this->feed('5000JBHA1 BA-1', 'zaterdag 7 november', '12:30', prefix: 'Vervallen: ');
        $result = $this->service()->sync($this->version);

        $this->assertSame(0, $result['added']);
        $this->assertSame(0, FixtureMatch::query()->count());
    }

    public function test_the_sync_leaves_a_cancelled_match_that_is_already_there_alone(): void
    {
        $existing = $this->match('JBHA1-BA-2', '2026-11-07', '12:30');

        $this->feed('5000JBHA1 BA-1', 'zaterdag 7 november', '12:30', prefix: 'Vervallen: ');
        $this->service()->sync($this->version);

        $this->assertSame(1, FixtureMatch::query()->count());
        $this->assertSame('2026-11-07', $existing->fresh()->date->toDateString());
    }

    /**
     * The planning file puts the address behind the venue name, the feed only
     * the name. Read as a venue change, the sync took over the "new" court:
     * the team's default court. That lost the planner's court choices.
     */
    public function test_a_venue_name_with_address_behind_it_is_no_venue_change(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $courts = $venue->courts()->createMany([['name' => '1', 'sort_order' => 1], ['name' => '3', 'sort_order' => 3]]);

        $match = $this->match('JBHA1-BA-2', '2026-11-07', '12:30');
        $match->update([
            'venue_id' => $venue->id,
            'court_id' => $courts[1]->id,
            'original_venue' => 'Middachtensingel, Middachtensingel 4, 6825HN  Arnhem',
            'original_date' => '2026-11-07',
            'original_time' => '12:30',
        ]);

        $this->feed('5000JBHA1 BA-1', 'zaterdag 7 november', '12:30');
        $this->service()->sync($this->version);

        $this->assertSame($courts[1]->id, $match->fresh()->court_id);
    }

    private function match(string $code, string $date, string $time): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => $code, 'date' => $date, 'time' => $time,
            'home_team_name' => 'AETOS JB 1', 'away_team_name' => 'SV Dynamo Apeldoorn JB 1',
        ]);
    }

    private function feed(string $code, string $date, string $time, string $prefix = ''): void
    {
        Http::fake(['api.nevobo.nl/*' => Http::response(<<<XML
            <rss><channel><item>
                <title><![CDATA[{$prefix}7 nov {$time}: AETOS JB 1 - SV Dynamo Apeldoorn JB 1]]></title>
                <description><![CDATA[Wedstrijd: {$code}, Datum: {$date}, {$time}, Speellocatie: Middachtensingel, Poule: JBHA1]]></description>
                <pubDate>Sat, 07 Nov 2026 12:30:00 +0100</pubDate>
            </item></channel></rss>
            XML)]);
    }

    private function service(): RssPlanningImportService
    {
        return app(RssPlanningImportService::class);
    }
}
