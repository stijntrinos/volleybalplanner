<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\PublishedFeedService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PublishedFeedServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $published;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-01 12:00');
        $this->useClub($this->createClub());
        $this->published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel']);
    }

    public function test_only_published_home_matches_are_in_the_feed(): void
    {
        $this->match($this->published, 'ABC1');
        $this->match($this->published, 'AWAY1', ['match_type' => MatchType::Away]);
        $this->match(ScheduleVersion::factory()->create(), 'DRAFT1');

        $service = app(PublishedFeedService::class);

        $this->assertSame(['ABC1'], $service->matches()->pluck('match_code')->all());
        $this->assertSame(['ABC1'], $service->matches('2026-09-12')->pluck('match_code')->all());
        $this->assertSame([], $service->matches('2026-09-13')->pluck('match_code')->all());
        $this->assertStringContainsString('ABC1', $service->xml('Thuiswedstrijden', 'Gepubliceerd', $service->matches()));
    }

    public function test_the_central_feed_uses_the_club_name_and_the_planner_namespace(): void
    {
        config(['app.url' => 'https://planner.example']);
        $this->match($this->published, 'ABC1', ['referee' => 'DS 1']);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'venue_id' => $this->venue->id, 'date' => '2026-09-12',
            'start_time' => '09:00', 'end_time' => '13:00', 'team_id' => Team::factory()->create(['code' => 'HS 2'])->id,
        ]);

        $xml = app(PublishedFeedService::class)->centralXml();
        $club = app(CurrentClub::class)->require();

        $this->assertStringContainsString('xmlns:planner="https://planner.example/rss/ns"', $xml);
        $this->assertStringContainsString('<title>'.$club->name.' wedstrijdplanning</title>', $xml);
        $this->assertStringContainsString('<planner:match_code>ABC1</planner:match_code>', $xml);
        $this->assertStringContainsString('<planner:referee>DS 1</planner:referee>', $xml);
        $this->assertStringContainsString('<planner:type>hall_duty</planner:type>', $xml);
        $this->assertStringContainsString('<planner:scheduleVersionId>'.$this->published->id.'</planner:scheduleVersionId>', $xml);
    }

    public function test_without_the_duties_module_the_central_feed_has_no_hall_duties(): void
    {
        $this->useClub($this->createClub(['rss_feeds']));
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $venue = Venue::factory()->create();
        HallDuty::factory()->create([
            'version_id' => $version->id, 'venue_id' => $venue->id, 'date' => '2026-09-12',
            'start_time' => '09:00', 'end_time' => '13:00', 'team_id' => Team::factory()->create()->id,
        ]);

        $this->assertStringNotContainsString('hall_duty', app(PublishedFeedService::class)->centralXml());
    }

    /** @param  array<string, mixed>  $extra */
    private function match(ScheduleVersion $version, string $code, array $extra = []): FixtureMatch
    {
        $court = Court::query()->firstOrCreate(['venue_id' => $this->venue->id, 'name' => '1']);

        return FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => $code, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $this->venue->id, 'court_id' => $court->id, 'match_type' => MatchType::Home,
            ...$extra,
        ]);
    }
}
