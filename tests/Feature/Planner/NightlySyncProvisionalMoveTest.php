<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Services\RssPlanningImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The nightly-sync part of the provisional move: a match moved to another day
 * waits for Nevobo. When the feed shows it on the proposed day it becomes
 * final; any other Nevobo date is left for review. The proposal is set
 * directly here (PlannerService::move() is tested with PlannerService).
 */
class NightlySyncProvisionalMoveTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court1;

    private Court $court2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00'));
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);

        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel', 'for_matches' => true]);
        $this->court1 = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1', 'sort_order' => 1]);
        $this->court2 = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '2', 'sort_order' => 2]);
    }

    public function test_the_nightly_sync_finalizes_a_proposal_when_nevobo_takes_it_over(): void
    {
        $match = $this->proposedMatch('2026-09-19', '12:00', $this->court2);

        $this->feed('zaterdag 19 september', '12:00', 'Sat, 19 Sep 2026 12:00:00 +0200');
        app(RssPlanningImportService::class)->sync($this->version);

        $match->refresh();
        $this->assertSame('2026-09-19', $match->date->toDateString());
        $this->assertSame($this->court2->id, $match->court_id, 'The chosen court stays, not the team default court.');
        $this->assertSame('2026-09-19', $match->original_date->toDateString());
        $this->assertNull($match->provisional_date);
        $this->assertNull($match->change_status);
        $this->assertSame(NevoboChangeStatus::Applied, $match->nevobo_change_status);
    }

    public function test_the_nightly_sync_leaves_another_nevobo_date_for_review(): void
    {
        $match = $this->proposedMatch('2026-09-19', '12:00', $this->court2);

        $this->feed('zaterdag 26 september', '10:00', 'Sat, 26 Sep 2026 10:00:00 +0200');
        app(RssPlanningImportService::class)->sync($this->version);

        $match->refresh();
        $this->assertSame('2026-09-12', $match->date->toDateString());
        $this->assertSame('2026-09-19', $match->provisional_date->toDateString());
        $this->assertSame(NevoboChangeStatus::Conflict, $match->nevobo_change_status);
    }

    private function proposedMatch(string $date, string $time, Court $court): FixtureMatch
    {
        $team = Team::factory()->create(['code' => 'JB 1', 'name' => 'AET']);
        TeamSource::factory()->create([
            'team_id' => $team->id, 'name' => 'Competitie', 'is_active' => true,
            'url' => 'https://api.nevobo.nl/export/poule/regio-oost/JBHA1/programma.rss',
        ]);

        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'JBHA1-BA',
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105, 'match_type' => MatchType::Home,
            'venue_id' => $this->venue->id, 'court_id' => $this->court1->id, 'second_court_id' => null,
            'home_team_name' => 'AETOS JB 1', 'away_team_name' => 'SV Dynamo Apeldoorn JB 1',
            'original_date' => '2026-09-12', 'original_time' => '10:00',
            'original_venue' => 'Middachtensingel', 'original_court' => '1',
            'provisional_date' => $date, 'provisional_time' => $time, 'provisional_duration_minutes' => 105,
            'provisional_venue_id' => $this->venue->id, 'provisional_court_id' => $court->id,
            'change_status' => ChangeStatus::Requested, 'change_requested' => true,
        ]);
    }

    private function feed(string $date, string $time, string $pubDate): void
    {
        Http::fake(['api.nevobo.nl/*' => Http::response(<<<XML
            <rss><channel><item>
                <title><![CDATA[AETOS JB 1 - SV Dynamo Apeldoorn JB 1]]></title>
                <description><![CDATA[Wedstrijd: 5000JBHA1 BA, Datum: {$date}, {$time}, Speellocatie: Middachtensingel, Poule: JBHA1]]></description>
                <pubDate>{$pubDate}</pubDate>
            </item></channel></rss>
            XML)]);
    }
}
