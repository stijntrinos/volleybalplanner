<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** The route and rate-limit part of the Aetos RssServiceTest. */
class PublicRssFeedTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-01 12:00');
    }

    public function test_the_public_feed_only_shows_published_home_matches(): void
    {
        $club = $this->useClub($this->createClub());
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-12', 'time' => '10:00',
            'venue_id' => $venue->id, 'court_id' => $court->id, 'match_code' => 'ABC1',
        ]);
        FixtureMatch::factory()->create([
            'version_id' => ScheduleVersion::factory()->create()->id, 'date' => '2026-09-12', 'match_code' => 'DRAFT1',
        ]);

        $response = $this->get("/{$club->slug}/rss/thuis");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('ABC1')
            ->assertDontSee('DRAFT1')
            ->assertSee($club->name);
        $this->assertStringContainsString("\n  <channel>\n", $response->getContent());
        $this->assertStringContainsString("\n    <item>\n", $response->getContent());
        $this->get("/{$club->slug}/rss/dag/2026-09-12")->assertOk()->assertSee('ABC1');
        $this->get("/{$club->slug}/rss/dag/2026-09-13")->assertOk()->assertDontSee('ABC1');
        $this->get("/{$club->slug}/rss/dag/morgen")->assertNotFound();
        $this->get("/{$club->slug}/rss/sporthal/de%20laar")->assertOk()->assertSee('ABC1');
        $this->get("/{$club->slug}/rss/sporthal/elders")->assertOk()->assertDontSee('ABC1');
        $this->get("/{$club->slug}/rss")->assertOk()->assertSee('ABC1')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_the_feeds_show_the_home_team_referee_license(): void
    {
        $club = $this->useClub($this->createClub());
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $team = Team::factory()->create(['code' => 'HS 1', 'referee_license' => 'R2.2']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-08', 'time' => '20:00',
            'venue_id' => $venue->id, 'home_team_id' => $team->id, 'match_code' => 'LIC1',
        ]);

        $this->get("/{$club->slug}/rss")->assertOk()->assertSee('<planner:referee_license>R2.2</planner:referee_license>', false);
        $this->get("/{$club->slug}/rss/thuis")->assertOk()->assertSee('Fluitlicentie: minimaal R2.2');
    }

    public function test_the_team_feed_matches_the_code_as_a_whole_word(): void
    {
        $club = $this->useClub($this->createClub());
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2026-09-12', 'match_code' => 'M-DS1', 'home_team_name' => 'Demo DS1']);
        FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2026-09-12', 'match_code' => 'M-DS10', 'home_team_name' => 'Demo DS10']);

        $this->get("/{$club->slug}/rss/team/DS1")->assertOk()->assertSee('Wedstrijdcode: M-DS1 ')->assertDontSee('M-DS10');
    }

    public function test_the_public_feeds_are_rate_limited(): void
    {
        $club = $this->publishedClub();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->get("/{$club->slug}/rss/thuis")->assertOk();
        }

        $this->get("/{$club->slug}/rss/thuis")->assertStatus(429);
    }

    public function test_the_rate_limit_is_kept_per_club(): void
    {
        $first = $this->publishedClub();
        $second = $this->publishedClub();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->get("/{$first->slug}/rss/thuis")->assertOk();
        }

        $this->get("/{$first->slug}/rss/thuis")->assertStatus(429);
        $this->get("/{$second->slug}/rss/thuis")->assertOk();
    }

    public function test_without_the_rss_module_the_feeds_do_not_exist(): void
    {
        $club = $this->useClub($this->createClub([PlannerFeature::Duties->value]));

        foreach (['rss', 'rss/thuis', 'rss/sporthal/x', 'rss/team/DS1', 'rss/dag/2026-09-12'] as $path) {
            $this->get("/{$club->slug}/{$path}")->assertNotFound();
        }
    }

    private function publishedClub(): Club
    {
        $club = $this->useClub($this->createClub());
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create(['version_id' => $version->id, 'date' => '2026-09-12', 'time' => '10:00']);

        return $club;
    }
}
