<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
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
use RuntimeException;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class RssPlanningImportServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private string $feedTime = '15:00';

    private string $feedLocation = 'Middachtensingel';

    private bool $feedRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
    }

    public function test_creates_and_updates_draft_version_from_active_team_sources(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $venue->courts()->createMany([['name' => '1', 'sort_order' => 1], ['name' => '2', 'sort_order' => 2]]);
        $team = Team::factory()->create([
            'code' => 'DS 1', 'name' => 'AET', 'default_venue_id' => $venue->id, 'default_court' => 1,
            'default_match_duration' => 150, 'plays_on_two_courts' => true,
        ]);
        $this->source($team, 'https://api.nevobo.nl/test/programma.rss');
        Http::fake(['api.nevobo.nl/*' => Http::response(<<<'XML'
            <rss><channel><item>
            <title><![CDATA[AETOS DS 1 - Tegenstander (ABC123)]]></title>
            <description><![CDATA[14-09-2026 15:00 Locatie: Middachtensingel
            Poule: 3CD]]></description>
            </item></channel></rss>
            XML)]);

        $first = $this->service()->sync();
        $second = $this->service()->sync($first['version']);

        $this->assertSame(1, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertSame(1, $second['updated']);
        $this->assertDatabaseCount('planner_schedule_versions', 1);
        $this->assertDatabaseCount('planner_matches', 1);
        $this->assertDatabaseHas('planner_matches', [
            'version_id' => $first['version']->id,
            'match_code' => 'ABC123',
            'date' => '2026-09-14 00:00:00',
            'duration_minutes' => 150,
            'venue_id' => $venue->id,
            'home_team_id' => $team->id,
            'match_type' => 'home',
            'original_venue' => 'Middachtensingel',
            'original_court' => '1',
        ]);
        $match = $first['version']->matches()->with(['court', 'secondCourt'])->firstOrFail();
        $this->assertSame('1', $match->court->name);
        $this->assertSame('2', $match->secondCourt->name);
    }

    public function test_resync_keeps_manual_planning_and_parks_nevobo_change_for_review(): void
    {
        $venue = $this->withSource(duration: 120);
        $this->feed('15:00');

        $version = $this->service()->sync()['version'];
        $match = $version->matches()->firstOrFail();
        $court2 = $venue->courts()->where('name', '2')->firstOrFail();
        $match->update(['time' => '19:30', 'court_id' => $court2->id]);

        // Nevobo moves the match to 16:00.
        $this->feed('16:00');
        $result = $this->service()->sync($version->fresh());

        $after = $match->fresh();
        $this->assertSame(1, $result['conflicts']);
        $this->assertSame('19:30', substr((string) $after->time, 0, 5), 'manual time must stay');
        $this->assertSame($court2->id, $after->court_id, 'manual court must stay');
        $this->assertSame(NevoboChangeStatus::Conflict, $after->nevobo_change_status);
        $this->assertSame('15:00', substr((string) $after->nevobo_previous_time, 0, 5));
        $this->assertSame('16:00', substr((string) $after->nevobo_new_time, 0, 5));
        $this->assertNotNull($after->nevobo_changed_at);
    }

    public function test_resync_applies_nevobo_when_nobody_moved_the_match(): void
    {
        $this->withSource(duration: 120);
        $this->feed('15:00');

        $version = $this->service()->sync()['version'];
        $match = $version->matches()->firstOrFail();

        $this->feed('16:00');
        $result = $this->service()->sync($version->fresh());

        $after = $match->fresh();
        $this->assertSame(1, $result['applied']);
        $this->assertSame('16:00', substr((string) $after->time, 0, 5));
        $this->assertSame('16:00', substr((string) $after->original_time, 0, 5), 'reference point moves along');
        $this->assertSame('15:00', substr((string) $after->nevobo_previous_time, 0, 5), 'the previous federation time stays visible');
        $this->assertSame('16:00', substr((string) $after->nevobo_new_time, 0, 5));
        $this->assertSame(NevoboChangeStatus::Applied, $after->nevobo_change_status);
        $this->assertFalse($after->deviatesFromNevobo());
    }

    public function test_resync_without_nevobo_change_touches_nothing(): void
    {
        $this->withSource(duration: 120);
        $this->feed('15:00');

        $version = $this->service()->sync()['version'];
        $match = $version->matches()->firstOrFail();
        $match->update(['time' => '19:30']);

        $result = $this->service()->sync($version->fresh());

        $this->assertSame(0, $result['applied']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame('19:30', substr((string) $match->fresh()->time, 0, 5));
        $this->assertSame(NevoboChangeStatus::None, $match->fresh()->nevobo_change_status);
    }

    public function test_resync_clears_an_outdated_false_dashboard_notice(): void
    {
        $this->withSource(duration: 120);
        $this->feed('15:00');

        $version = $this->service()->sync()['version'];
        $match = $version->matches()->firstOrFail();
        $match->update([
            'nevobo_change_status' => NevoboChangeStatus::Conflict,
            'nevobo_changed_at' => now(),
            'nevobo_new_date' => '2026-10-01',
        ]);

        $this->service()->sync($version->fresh());

        $match->refresh();
        $this->assertSame(NevoboChangeStatus::None, $match->nevobo_change_status);
        $this->assertNull($match->nevobo_changed_at);
        $this->assertNull($match->nevobo_new_date);
    }

    public function test_sync_creates_no_venue_for_an_unknown_location(): void
    {
        $this->withSource(duration: 120);
        $this->feedLocation = 'Sporthal Van De Tegenstander';
        $this->feed('15:00');

        $before = Venue::query()->count();
        $result = $this->service()->sync();

        $this->assertSame($before, Venue::query()->count(), 'an unknown location must not yield a venue');
        $match = $result['version']->matches()->firstOrFail();
        $this->assertNull($match->venue_id);
        $this->assertSame(MatchType::Away, $match->match_type, 'an unknown location means an away match');
    }

    public function test_a_moved_match_without_code_gives_no_duplicate(): void
    {
        $team = Team::factory()->create(['code' => 'HS 1']);
        $this->source($team, 'https://example.test/feed.xml');

        // The same match, first on 12-09 then moved to 19-09, without a code.
        $feed = fn (string $date): string => '<?xml version="1.0"?><rss><channel><item>'
            .'<title>AETOS HS 1 - Dynamo HS 1</title>'
            ."<description>Datum {$date} 10:00 | Poule: H1 | Locatie: De Laar</description>"
            .'</item></channel></rss>';

        Http::fake(['example.test/*' => Http::response($feed('12-09-2026'))]);
        $version = $this->service()->sync()['version'];

        Http::fake(['example.test/*' => Http::response($feed('19-09-2026'))]);
        $second = $this->service()->sync($version->fresh());

        $this->assertSame(0, $second['added'], 'A moved match should not give a second row');
        $this->assertSame(1, $version->fresh()->matches()->count());
    }

    public function test_pool_match_without_own_team_is_not_imported(): void
    {
        $team = Team::factory()->create(['code' => 'JA 1']);
        $this->source($team, 'https://example.test/feed.xml');

        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel>'
            .'<item><title>AETOS JA 1 - Gemini JA 1 (EIGEN1)</title>'
            .'<description>Datum 12-09-2026 10:00 | Poule: JA1 | Locatie: De Laar</description></item>'
            .'<item><title>Gemini JA 1 - Toesj JA 1 (VREEMD1)</title>'
            .'<description>Datum 12-09-2026 12:00 | Poule: JA1 | Locatie: Gemini-hal</description></item>'
            .'</channel></rss>')]);

        $result = $this->service()->sync();

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('planner_matches', ['match_code' => 'EIGEN1']);
        $this->assertDatabaseMissing('planner_matches', ['match_code' => 'VREEMD1']);
    }

    public function test_pool_feed_links_a_match_to_the_team_from_the_name_not_the_source_team(): void
    {
        $hs7 = Team::factory()->create(['code' => 'HS 7', 'name' => 'AETOS HS 7']);
        $hs8 = Team::factory()->create(['code' => 'HS 8', 'name' => 'AETOS HS 8']);
        $this->source($hs7, 'https://example.test/hs7.xml');
        $this->fakeHs8Feed();

        $match = $this->service()->sync()['version']->matches()->firstOrFail();

        $this->assertSame($hs8->id, $match->away_team_id);
        $this->assertNotSame($hs7->id, $match->away_team_id, 'the HS 7 pool feed does not make this an HS 7 match');
    }

    public function test_resync_repairs_an_existing_wrong_team_link_from_a_pool_feed(): void
    {
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $hs7 = Team::factory()->create(['code' => 'HS 7', 'name' => 'AETOS HS 7']);
        $hs8 = Team::factory()->create(['code' => 'HS 8', 'name' => 'AETOS HS 8']);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'H2H-EF',
            'date' => '2026-09-24', 'time' => '19:30', 'home_team_name' => 'Boemerang Komeet HS 2',
            'away_team_name' => 'AETOS HS 8', 'away_team_id' => $hs7->id, 'match_type' => MatchType::Away,
            'original_date' => '2026-09-24', 'original_time' => '19:30', 'original_venue' => 'De Vallei',
        ]);
        $this->source($hs7, 'https://example.test/hs7.xml');
        $this->fakeHs8Feed();

        $this->service()->sync($version);

        $this->assertSame($hs8->id, $match->refresh()->away_team_id);
    }

    public function test_another_spelling_of_the_venue_is_no_change(): void
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel', 'for_matches' => true]);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'ABC1',
            'date' => '2026-09-12', 'time' => '10:00', 'venue_id' => $venue->id,
            'original_date' => '2026-09-12', 'original_time' => '10:00', 'original_venue' => 'Middachtensingel',
        ]);
        $this->source(Team::factory()->create(['code' => 'HS 1']), 'https://example.test/feed.xml');

        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel><item>'
            .'<title>AETOS HS 1 - Dynamo HS 1 (ABC1)</title>'
            .'<description>Datum 12-09-2026 10:00 | Locatie: midachtensingel</description>'
            .'</item></channel></rss>')]);

        $this->service()->sync($version);

        $this->assertSame(NevoboChangeStatus::None, $match->refresh()->nevobo_change_status);
    }

    public function test_the_code_from_the_current_feed_recognises_the_existing_match(): void
    {
        $venue = Venue::factory()->create(['name' => 'Valkenhuizen', 'for_matches' => true]);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'H3D-GA',
            'date' => '2026-10-29', 'time' => '20:30', 'venue_id' => $venue->id,
            'home_team_name' => 'AETOS HS 9', 'away_team_name' => 'VoCASA HS 6', 'match_type' => MatchType::Home,
            'original_date' => '2026-10-29', 'original_time' => '20:30', 'original_venue' => 'Valkenhuizen',
        ]);
        $this->source(Team::factory()->create(['code' => 'HS 9']), 'https://example.test/feed.xml');

        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel><item>'
            .'<title>29 okt 20:30: AETOS HS 9 - VoCASA HS 6</title>'
            .'<description>Wedstrijd: H3D   GA, Datum: donderdag 29 oktober, 20:30, Speellocatie: Valkenhuizen, Beukenlaan 15, Arnhem</description>'
            .'<pubDate>Thu, 29 Oct 2026 20:30:00 +0200</pubDate>'
            .'</item></channel></rss>')]);

        $result = $this->service()->sync($version);

        $this->assertSame(0, $result['added'], 'the known match should not give a second row');
        $this->assertSame(1, $version->fresh()->matches()->count());
        $this->assertSame('H3D-GA', $match->refresh()->match_code, 'the federation code stays');
        $this->assertSame(MatchType::Home, $match->match_type, 'an unreadable feed location does not make it an away match');
        $this->assertSame($venue->id, $match->venue_id);
    }

    public function test_match_code_picks_the_right_fixture_between_the_same_teams(): void
    {
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $first = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'H1-AB', 'date' => '2026-09-12', 'time' => '10:00',
            'home_team_name' => 'AETOS HS 1', 'away_team_name' => 'Dynamo HS 1',
        ]);
        $return = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'H1-CD', 'date' => '2026-11-14', 'time' => '10:00',
            'home_team_name' => 'AETOS HS 1', 'away_team_name' => 'Dynamo HS 1',
        ]);
        $this->source(Team::factory()->create(['code' => 'HS 1']), 'https://example.test/feed.xml');

        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel><item>'
            .'<title>AETOS HS 1 - Dynamo HS 1</title>'
            .'<description>Wedstrijd: H1   CD, Datum 14-11-2026 12:00 | Poule: H1 | Locatie: De Laar</description>'
            .'</item></channel></rss>')]);

        $result = $this->service()->sync($version);

        $this->assertSame(0, $result['added']);
        $this->assertSame(2, $version->fresh()->matches()->count());
        // Only the return match should see the federation's new time.
        $this->assertSame('12:00', FixtureMatch::shortTime($return->refresh()->nevobo_new_time));
        $this->assertNull($first->refresh()->nevobo_changed_at, 'the first match should stay untouched');
    }

    public function test_only_a_venue_change_is_taken_over_without_dashboard_notice(): void
    {
        $oldVenue = $this->withSource(duration: 120);
        $newVenue = Venue::factory()->create(['name' => 'De Laar', 'for_matches' => true]);
        $newCourt = $newVenue->courts()->create(['name' => '1', 'sort_order' => 1]);
        $this->feedLocation = $oldVenue->name;
        $this->feed('15:00');

        $version = $this->service()->sync()['version'];
        $match = $version->matches()->firstOrFail();

        $this->feedLocation = $newVenue->name;
        $result = $this->service()->sync($version->fresh());

        $match->refresh();
        $this->assertSame(0, $result['applied']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame($newVenue->id, $match->venue_id);
        $this->assertSame($newCourt->id, $match->court_id);
        $this->assertSame(NevoboChangeStatus::None, $match->nevobo_change_status);
        $this->assertNull($match->nevobo_changed_at);
    }

    public function test_a_published_version_is_never_synced_into(): void
    {
        $this->withSource(duration: 120);
        $this->feed('15:00');
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);

        $result = $this->service()->sync($published);

        $this->assertNotSame($published->id, $result['version']->id);
        $this->assertSame(0, $published->matches()->count());
    }

    public function test_without_active_sources_the_sync_refuses(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service()->sync();
    }

    private function withSource(int $duration): Venue
    {
        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $venue->courts()->createMany([['name' => '1', 'sort_order' => 1], ['name' => '2', 'sort_order' => 2]]);
        $team = Team::factory()->create([
            'code' => 'DS 1', 'name' => 'AET', 'default_venue_id' => $venue->id, 'default_court' => 1,
            'default_match_duration' => $duration,
        ]);
        $this->source($team, 'https://api.nevobo.nl/test/programma.rss');

        return $venue;
    }

    private function source(Team $team, string $url): TeamSource
    {
        return TeamSource::factory()->create(['team_id' => $team->id, 'name' => 'Competitie', 'url' => $url, 'is_active' => true]);
    }

    private function fakeHs8Feed(): void
    {
        Http::fake(['example.test/*' => Http::response('<?xml version="1.0"?><rss><channel><item>'
            .'<title>Boemerang Komeet HS 2 - AETOS HS 8 (H2H-EF)</title>'
            .'<description>Datum 24-09-2026 19:30 | Poule: H2H | Locatie: De Vallei</description>'
            .'</item></channel></rss>')]);
    }

    /**
     * Http::fake() merges stubs and the first match wins, so registering a
     * second feed would not replace the first. One stub reads these
     * properties instead, which lets a test change what Nevobo reports.
     */
    private function feed(string $time): void
    {
        $this->feedTime = $time;

        if ($this->feedRegistered) {
            return;
        }

        $this->feedRegistered = true;
        Http::fake(['api.nevobo.nl/*' => fn () => Http::response(
            '<rss><channel><item>'
            .'<title><![CDATA[AETOS DS 1 - Tegenstander (ABC123)]]></title>'
            ."<description><![CDATA[14-09-2026 {$this->feedTime} Locatie: {$this->feedLocation}\nPoule: 3CD]]></description>"
            .'</item></channel></rss>',
        )]);
    }

    private function service(): RssPlanningImportService
    {
        return app(RssPlanningImportService::class);
    }
}
