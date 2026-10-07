<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Services\ImportService;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

class ImportServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    protected function setUp(): void
    {
        parent::setUp();
        // The import ignores matches before today. Without a fixed "now" the
        // dates in these fixtures become the past one day and silently empty
        // the import file.
        $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
    }

    public function test_dry_run_normalizes_rows_and_reports_errors(): void
    {
        $file = $this->planningCsv([
            ['12-09-2026', '00:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem'],
            ['12-09-2026', '10:00', 'AETOS HS1', '', 'De Laar', '1', 'Oost', 'H1', 'ABC2', 'DL', 'Arnhem'],
        ]);

        $report = $this->service()->dryRun($file);

        $this->assertSame(1, $report['usable']);
        $this->assertNull($report['rows'][0]['time']);
        $this->assertSame(MatchType::Home->value, $report['rows'][0]['type']);
        $this->assertCount(1, $report['skipped']);
        $this->assertSame(['De Laar'], $report['new_venues']);
    }

    public function test_dry_run_matches_venue_by_code_alias_and_normalized_name(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel', 'venue_code' => 'MID']);
        VenueAlias::create(['venue_id' => $venue->id, 'name' => 'Sportcentrum Presikhaaf']);
        $file = $this->planningCsv([
            ['12-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'Verkeerde naam', '1', 'Oost', 'H1', 'ABC1', 'MID', 'Arnhem'],
            ['12-09-2026', '12:00', 'AETOS HS2', 'Dynamo HS2', 'Sportcentrum Presikhaaf', '1', 'Oost', 'H2', 'ABC2', '', 'Arnhem'],
            ['12-09-2026', '14:00', 'AETOS HS3', 'Dynamo HS3', 'Midachtensingel', '1', 'Oost', 'H3', 'ABC3', '', 'Arnhem'],
        ]);

        $report = $this->service()->dryRun($file);

        $this->assertSame([], $report['new_venues']);
        $this->assertCount(2, $report['name_variants']);
        $this->assertSame(['alias', 'normalized'], array_column($report['name_variants'], 'method'));
    }

    public function test_new_import_creates_version_matches_team_and_applies_defaults(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $court1 = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        Court::factory()->create(['venue_id' => $venue->id, 'name' => '2']);
        $team = Team::factory()->create([
            'code' => 'HS1', 'default_match_duration' => 105,
            'plays_on_two_courts' => true, 'scores_own_match' => true,
        ]);
        $file = $this->planningCsv([['12-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem']]);

        $version = $this->service()->importNewVersion($file, 'Import september');
        $match = $version->matches()->firstOrFail();

        $this->assertSame($team->id, $match->home_team_id);
        $this->assertSame($court1->id, $match->court_id);
        $this->assertNotNull($match->second_court_id);
        $this->assertSame(105, $match->duration_minutes);
        $this->assertSame('HS1', $match->scorer);
        $this->assertTrue($match->scorer_auto);
        $this->assertTrue($this->service()->verify($file, $version)['equal']);
    }

    public function test_verification_accepts_a_venue_alias_for_the_stored_venue(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel', 'city' => 'Arnhem']);
        VenueAlias::create(['venue_id' => $venue->id, 'name' => 'Sportcentrum Presikhaaf']);
        $file = $this->planningCsv([
            ['12-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'Sportcentrum Presikhaaf', '1', 'Oost', 'H1', 'ABC1', '', 'Arnhem'],
        ]);

        $version = $this->service()->importNewVersion($file, 'Aliasimport');

        $this->assertSame($venue->id, $version->matches()->firstOrFail()->venue_id);
        $this->assertTrue($this->service()->verify($file, $version)['equal']);
    }

    public function test_import_uses_previous_planning_as_original(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $previous = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create([
            'version_id' => $previous->id, 'match_code' => 'ABC1',
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);
        $file = $this->planningCsv([['19-09-2026', '12:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem']]);

        $match = $this->service()->importNewVersion($file, 'Nieuwe import')->matches()->firstOrFail();

        $this->assertSame('2026-09-12', $match->original_date->toDateString());
        $this->assertSame('10:00', substr($match->original_time, 0, 5));
        $this->assertSame('2026-09-19', $match->date->toDateString());
    }

    public function test_sitting_volleyball_is_only_home_in_a_venue_for_matches(): void
    {
        Venue::factory()->create(['name' => 'Middachtensingel', 'for_matches' => true]);
        Venue::factory()->create(['name' => 'Sporthal Elderveld', 'for_matches' => false]);
        $file = $this->planningCsv([
            ['12-09-2026', '10:00', 'AETOS XZ1', 'Tegenstander', 'Middachtensingel', '1', 'Oost', 'Z1', 'ZIT1', 'MID', 'Arnhem'],
            ['19-09-2026', '12:00', 'Tegenstander', 'AETOS XZ 1', 'Sporthal Elderveld', '1', 'Oost', 'Z1', 'ZIT2', 'ELD', 'Arnhem'],
        ]);

        $matches = $this->service()->importNewVersion($file, 'Zitvolleybal')->matches()->orderBy('match_code')->get();

        $this->assertSame(MatchType::Home, $matches[0]->match_type);
        $this->assertTrue($matches[0]->is_sitting_volleyball);
        $this->assertSame(MatchType::Away, $matches[1]->match_type);
        $this->assertFalse($matches[1]->is_sitting_volleyball);
    }

    public function test_away_matches_create_no_external_venues_or_courts(): void
    {
        $file = $this->planningCsv([
            ['12-09-2026', '10:00', 'Dynamo HS1', 'AETOS HS1', 'Omnisport', 'Veld 3', 'Oost', 'H1', 'UIT1', 'OMN', 'Apeldoorn'],
        ]);

        $report = $this->service()->dryRun($file);
        $match = $this->service()->importNewVersion($file, 'Uitwedstrijden')->matches()->firstOrFail();

        $this->assertSame([], $report['new_venues']);
        $this->assertSame(MatchType::Away, $match->match_type);
        $this->assertNull($match->venue_id);
        $this->assertNull($match->court_id);
        $this->assertSame('Omnisport', $match->original_venue);
        $this->assertDatabaseCount('planner_venues', 0);
        $this->assertDatabaseCount('planner_courts', 0);
        $this->assertTrue($this->service()->verify($file, $match->version)['equal']);
    }

    public function test_update_categorizes_source_update_and_follows_the_source(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'ABC1',
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $venue->id, 'court_id' => $court->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00',
        ]);
        $file = $this->planningCsv([['19-09-2026', '12:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem']]);

        $counts = $this->service()->updateVersion($file, $version);

        $this->assertSame(1, $counts[ImportService::CATEGORY_SOURCE_UPDATE]);
        $this->assertSame('2026-09-19', $match->refresh()->date->toDateString());
        $this->assertSame('12:00', substr($match->time, 0, 5));
    }

    public function test_an_unreadable_date_skips_only_that_row(): void
    {
        $file = $this->planningCsv([
            ['nader te bepalen', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem'],
            ['12-09-2026', '10:00', 'AETOS HS2', 'Dynamo HS2', 'De Laar', '1', 'Oost', 'H2', 'ABC2', 'DL', 'Arnhem'],
        ]);

        $report = $this->service()->dryRun($file);

        $this->assertSame(1, $report['usable']);
        $this->assertSame('ABC2', $report['rows'][0]['code']);
        $this->assertCount(1, $report['skipped']);
        $this->assertSame(2, $report['skipped'][0]['row']);
        $this->assertStringContainsString('Datum is onleesbaar', $report['skipped'][0]['reason']);
    }

    public function test_rows_dated_before_today_are_ignored(): void
    {
        $file = $this->planningCsv([
            ['05-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'OUD1', 'DL', 'Arnhem'],
            ['10-09-2026', '20:00', 'AETOS HS2', 'Dynamo HS2', 'De Laar', '1', 'Oost', 'H2', 'VANDAAG1', 'DL', 'Arnhem'],
            ['19-09-2026', '10:00', 'AETOS HS3', 'Dynamo HS3', 'De Laar', '1', 'Oost', 'H3', 'NIEUW1', 'DL', 'Arnhem'],
        ]);

        $report = $this->service()->dryRun($file);
        $version = $this->service()->importNewVersion($file, 'Halverwege het seizoen');

        $this->assertSame(2, $report['usable'], 'A match of today still counts');
        $this->assertSame(['OUD1'], array_column($report['past'], 'code'));
        $this->assertSame([], $report['skipped'], 'A played match is no error in the file');
        $this->assertSame(['NIEUW1', 'VANDAAG1'], $version->matches()->orderBy('match_code')->pluck('match_code')->all());
    }

    public function test_update_leaves_played_matches_alone(): void
    {
        Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $played = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'OUD1', 'date' => '2026-09-05', 'time' => '10:00',
        ]);
        $file = $this->planningCsv([['19-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'NIEUW1', 'DL', 'Arnhem']]);

        $counts = $this->service()->updateVersion($file, $version->refresh());

        $this->assertSame(0, $counts[ImportService::CATEGORY_REMOVED], 'A played match needs no choice');
        $this->assertDatabaseHas('planner_matches', ['id' => $played->id]);
        $this->assertTrue($this->service()->verify($file, $version->refresh())['equal']);
    }

    public function test_unknown_teams_are_recognised_by_the_club_name_patterns(): void
    {
        Team::factory()->create(['code' => 'HS1']);
        $file = $this->planningCsv([
            ['12-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem'],
            ['12-09-2026', '12:00', 'AETOS HS9', 'Dynamo HS9', 'De Laar', '1', 'Oost', 'H9', 'ABC9', 'DL', 'Arnhem'],
        ]);

        $this->assertSame(['AETOS HS9'], $this->service()->dryRun($file)['unknown_teams']);
    }

    public function test_an_import_never_uses_or_changes_another_clubs_data(): void
    {
        [$otherVenue, $otherTeam, $otherMatch] = app(CurrentClub::class)->run($this->createClub(), function (): array {
            $venue = Venue::factory()->create(['name' => 'De Laar', 'city' => 'Arnhem', 'venue_code' => null]);
            $team = Team::factory()->create(['code' => 'HS1']);
            $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
            $match = FixtureMatch::factory()->create([
                'version_id' => $published->id, 'match_code' => 'ABC1', 'date' => '2026-09-12', 'time' => '10:00',
            ]);

            return [$venue, $team, $match];
        });
        $file = $this->planningCsv([['19-09-2026', '12:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', 'DL', 'Arnhem']]);

        $this->assertSame(['De Laar'], $this->service()->dryRun($file)['new_venues']);
        $match = $this->service()->importNewVersion($file, 'Eigen import')->matches()->firstOrFail();

        $this->assertNotSame($otherVenue->id, $match->venue_id);
        $this->assertNull($match->home_team_id, 'a team of another club is unknown here');
        $this->assertSame('2026-09-19', $match->original_date->toDateString(), 'another club\'s published version is no reference point');
        $this->assertSame('DL', Venue::withoutGlobalScopes()->find($match->venue_id)->venue_code);
        $this->assertNull(Venue::withoutGlobalScopes()->find($otherVenue->id)->venue_code, 'the other club\'s venue is not completed');
        $this->assertSame('2026-09-12', FixtureMatch::withoutGlobalScopes()->find($otherMatch->id)->date->toDateString());
        $this->assertSame(1, Team::withoutGlobalScopes()->whereKey($otherTeam->id)->count());
    }

    private function service(): ImportService
    {
        return app(ImportService::class);
    }
}
