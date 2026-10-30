<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Exceptions\PublishBlocked;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ScheduleVersionService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ScheduleVersionServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->service = app(ScheduleVersionService::class);
    }

    public function test_publishing_sets_status_and_published_at(): void
    {
        $version = ScheduleVersion::factory()->create();

        $this->service->publish($version);

        $version->refresh();
        $this->assertTrue($version->isPublished());
        $this->assertNotNull($version->published_at);
        $this->assertTrue($version->is_published);
    }

    public function test_publishing_archives_the_previously_published_version(): void
    {
        $old = ScheduleVersion::factory()->create();
        $new = ScheduleVersion::factory()->create();

        $this->service->publish($old);
        $this->service->publish($new);

        $this->assertTrue($old->refresh()->isArchived());
        $this->assertNull($old->is_published);
        $this->assertNotNull($old->archived_at);
        $this->assertTrue($new->refresh()->isPublished());
    }

    public function test_publishing_does_not_touch_other_clubs(): void
    {
        $versionA = ScheduleVersion::factory()->create();
        $clubB = $this->createClub();
        $versionB = app(CurrentClub::class)->run($clubB, fn () => ScheduleVersion::factory()->create());

        $this->service->publish($versionA);
        app(CurrentClub::class)->run($clubB, fn () => $this->service->publish($versionB));

        $this->assertTrue($versionA->refresh()->isPublished());
        $this->assertTrue(app(CurrentClub::class)->run($clubB, fn () => $versionB->refresh()->isPublished()));
    }

    public function test_the_database_enforces_at_most_one_published_version_per_club(): void
    {
        ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);

        $this->expectException(QueryException::class);

        ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
    }

    public function test_duplicating_creates_a_draft_with_copied_matches(): void
    {
        $source = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->count(3)->create(['version_id' => $source->id]);

        $copy = $this->service->duplicate($source, 'Kopie van concept');

        $this->assertTrue($copy->isDraft());
        $this->assertSame('Kopie van concept', $copy->name);
        $this->assertSame(3, $copy->matches()->count());
        $this->assertSame(3, $source->matches()->count());
        $this->assertSame(
            $source->matches()->orderBy('match_code')->pluck('match_code')->all(),
            $copy->matches()->orderBy('match_code')->pluck('match_code')->all(),
        );
    }

    public function test_archiving_and_reactivating(): void
    {
        $version = ScheduleVersion::factory()->create();

        $this->service->archive($version);
        $this->assertTrue($version->refresh()->isArchived());
        $this->assertNotNull($version->archived_at);

        $this->service->reactivate($version);
        $this->assertTrue($version->refresh()->isDraft());
        $this->assertNull($version->archived_at);
    }

    public function test_archiving_a_published_version_releases_is_published(): void
    {
        $version = ScheduleVersion::factory()->create();
        $this->service->publish($version);

        $this->service->archive($version->refresh());

        $this->assertNull($version->refresh()->is_published);
    }

    public function test_publishing_is_blocked_on_hard_conflicts(): void
    {
        $version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        FixtureMatch::factory()->count(2)->create([
            'version_id' => $version->id,
            'date' => '2026-09-12', 'time' => '10:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court->id,
        ]);

        try {
            $this->service->publish($version);
            $this->fail('Publishing should have been blocked.');
        } catch (PublishBlocked $blocked) {
            // The exception carries the scan result, so the overview in the
            // admin can show which match holds up publishing.
            $this->assertNotEmpty($blocked->conflicts);
            $this->assertSame(
                FixtureMatch::query()->where('version_id', $version->id)->orderBy('id')->pluck('id')->all(),
                collect($blocked->conflicts)->pluck('subject_match_id')->sort()->values()->all(),
            );
        }
    }

    public function test_duplicating_also_takes_open_nevobo_changes_along(): void
    {
        $source = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create([
            'version_id' => $source->id, 'match_code' => 'ABC1',
            'nevobo_change_status' => NevoboChangeStatus::Conflict,
            'nevobo_new_date' => '2026-10-03', 'nevobo_new_time' => '14:00',
            'nevobo_new_venue' => 'De Laar',
        ]);

        $copy = $this->service->duplicate($source, 'Kopie');

        $copied = $copy->matches()->where('match_code', 'ABC1')->firstOrFail();
        $this->assertSame(NevoboChangeStatus::Conflict, $copied->nevobo_change_status);
        $this->assertSame('2026-10-03', $copied->nevobo_new_date?->toDateString());
        $this->assertSame('De Laar', $copied->nevobo_new_venue);
    }
}
