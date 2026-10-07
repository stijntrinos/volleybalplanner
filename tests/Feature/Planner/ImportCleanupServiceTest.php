<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Enums\Source;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ImportCleanupService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ImportCleanupServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_used_import_locations_stay_and_are_removed_after_cleaning(): void
    {
        $venue = Venue::factory()->create(['source' => Source::MatchImport]);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'source' => Source::MatchImport]);
        $version = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id, 'court_id' => $court->id]);

        $first = $this->service()->cleanup(['courts', 'venues']);
        $second = $this->service()->cleanup(['matches', 'courts', 'venues']);

        $this->assertSame(['courts' => 0, 'venues' => 0], $first);
        $this->assertSame(1, $second['matches']);
        $this->assertSame(1, $second['courts']);
        $this->assertSame(1, $second['venues']);
        $this->assertDatabaseMissing('planner_venues', ['id' => $venue->id]);
    }

    public function test_manual_locations_are_never_removed_by_import_cleanup(): void
    {
        $venue = Venue::factory()->create(['source' => Source::Manual]);

        $this->service()->cleanup(['venues']);

        $this->assertDatabaseHas('planner_venues', ['id' => $venue->id]);
    }

    public function test_the_published_version_and_its_matches_survive_cleanup(): void
    {
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $stays = FixtureMatch::factory()->create(['version_id' => $published->id]);
        $goes = FixtureMatch::factory()->create(['version_id' => $draft->id]);

        $deleted = $this->service()->cleanup(['versions', 'matches']);

        $this->assertSame(1, $deleted['versions']);
        $this->assertSame(1, $deleted['matches']);
        $this->assertDatabaseHas('planner_schedule_versions', ['id' => $published->id]);
        $this->assertDatabaseHas('planner_matches', ['id' => $stays->id]);
        $this->assertDatabaseMissing('planner_schedule_versions', ['id' => $draft->id]);
        $this->assertDatabaseMissing('planner_matches', ['id' => $goes->id]);
    }

    public function test_counts_leave_out_the_published_version(): void
    {
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        FixtureMatch::factory()->create(['version_id' => $published->id]);
        FixtureMatch::factory()->count(2)->create(['version_id' => $draft->id]);

        $counts = $this->service()->counts();

        $this->assertSame(1, $counts['versions']);
        $this->assertSame(2, $counts['matches']);
        $this->assertTrue($this->service()->hasPublishedVersion());
    }

    public function test_cascade_counts_show_what_goes_along_with_the_versions(): void
    {
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        FixtureMatch::factory()->count(3)->create(['version_id' => $draft->id]);

        $this->assertSame(['matches' => 3], $this->service()->cascadeCounts());
    }

    public function test_external_import_venues_are_detached_and_removed(): void
    {
        $venue = Venue::factory()->create([
            'source' => Source::MatchImport, 'is_managed' => false, 'has_duties' => false,
            'for_matches' => false, 'for_trainings' => true,
        ]);
        $court = Court::factory()->create(['venue_id' => $venue->id, 'source' => Source::MatchImport]);
        $version = ScheduleVersion::factory()->create();
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_type' => MatchType::Away,
            'venue_id' => $venue->id, 'court_id' => $court->id, 'original_venue' => 'Externe hal',
        ]);

        $this->assertSame(1, $this->service()->counts()['external_venues']);

        $result = $this->service()->cleanupExternalVenues();

        $this->assertSame(['venues' => 1, 'courts' => 1, 'matches' => 1], $result);
        $this->assertNull($match->refresh()->venue_id);
        $this->assertNull($match->court_id);
        $this->assertSame('Externe hal', $match->original_venue);
        $this->assertDatabaseMissing('planner_venues', ['id' => $venue->id]);
    }

    public function test_own_and_operationally_used_import_venues_are_not_cleaned(): void
    {
        $own = Venue::factory()->create(['source' => Source::MatchImport, 'for_matches' => true]);
        $training = Venue::factory()->create(['source' => Source::MatchImport, 'for_trainings' => true]);

        $result = $this->service()->cleanupExternalVenues();

        $this->assertSame(['venues' => 0, 'courts' => 0, 'matches' => 0], $result);
        $this->assertDatabaseHas('planner_venues', ['id' => $own->id]);
        $this->assertDatabaseHas('planner_venues', ['id' => $training->id]);
    }

    public function test_module_categories_follow_the_club_features(): void
    {
        $this->useClub($this->createClub([]));

        $categories = array_keys($this->service()->categories());

        $this->assertNotContains('hall_duties', $categories);
        $this->assertNotContains('volleystars', $categories);
        $this->assertNotContains('trainings', $categories);
        $this->assertContains('matches', $categories);

        $this->useClub($this->createClub([PlannerFeature::Duties->value]));
        $this->assertArrayHasKey('hall_duties', $this->service()->categories());
    }

    public function test_cleanup_never_touches_another_club(): void
    {
        $other = $this->createClub();
        [$otherVenue, $otherMatch, $otherVersion] = app(CurrentClub::class)->run($other, function (): array {
            $venue = Venue::factory()->create(['source' => Source::MatchImport, 'for_matches' => false]);
            $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
            $match = FixtureMatch::factory()->create(['version_id' => $version->id, 'match_type' => MatchType::Away, 'venue_id' => $venue->id]);

            return [$venue, $match, $version];
        });
        ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);

        $this->assertSame(1, $this->service()->counts()['versions']);
        $deleted = $this->service()->cleanup(array_keys($this->service()->categories()));

        $this->assertSame(1, $deleted['versions']);
        $this->assertDatabaseHas('planner_venues', ['id' => $otherVenue->id]);
        $this->assertDatabaseHas('planner_matches', ['id' => $otherMatch->id, 'venue_id' => $otherVenue->id]);
        $this->assertDatabaseHas('planner_schedule_versions', ['id' => $otherVersion->id]);
    }

    private function service(): ImportCleanupService
    {
        return app(ImportCleanupService::class);
    }
}
