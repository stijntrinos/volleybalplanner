<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\ImportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

/**
 * In a conflict both the federation and the committee moved the match. Who
 * wins should be a choice, not a silent assumption.
 */
class ImportChoicesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    private ScheduleVersion $version;

    private FixtureMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);

        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);

        // The federation put it on 19-09, the committee moved it to 03-10.
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'ABC1',
            'date' => '2026-10-03', 'time' => '10:00', 'venue_id' => $venue->id,
            'original_date' => '2026-09-19', 'original_time' => '10:00', 'original_venue' => 'De Laar',
        ]);
    }

    public function test_in_a_conflict_the_source_can_still_be_followed(): void
    {
        $this->service()->updateVersion($this->file(), $this->version, [$this->match->id => ImportService::CHOICE_SOURCE]);

        $this->assertSame('2026-09-26', $this->match->refresh()->date->toDateString());
        $this->assertSame('2026-09-26', $this->match->original_date->toDateString());
    }

    public function test_without_a_choice_the_own_planning_stays(): void
    {
        $this->service()->updateVersion($this->file(), $this->version);

        $this->assertSame('2026-10-03', $this->match->refresh()->date->toDateString(), 'The own planning should stay');
        $this->assertSame('2026-09-26', $this->match->original_date->toDateString(), 'The reference point does follow the federation');
    }

    public function test_a_removed_match_can_be_deleted(): void
    {
        $removed = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'match_code' => 'WEG1']);

        $this->service()->updateVersion($this->file(), $this->version, [$removed->id => ImportService::CHOICE_DELETE]);

        $this->assertDatabaseMissing('planner_matches', ['id' => $removed->id]);
    }

    public function test_a_removed_match_stays_by_default(): void
    {
        $removed = FixtureMatch::factory()->create(['version_id' => $this->version->id, 'match_code' => 'WEG1']);

        $this->service()->updateVersion($this->file(), $this->version);

        $this->assertDatabaseHas('planner_matches', ['id' => $removed->id]);
    }

    public function test_deleting_everything_does_not_touch_a_played_match(): void
    {
        $played = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'OUD1', 'date' => '2026-09-05', 'time' => '10:00',
        ]);

        $this->service()->updateVersion($this->file(), $this->version, [$played->id => ImportService::CHOICE_DELETE]);

        $this->assertDatabaseHas('planner_matches', ['id' => $played->id]);
    }

    public function test_only_a_draft_can_be_updated(): void
    {
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->updateVersion($this->file(), $published);
    }

    private function file(): string
    {
        return $this->planningCsv([['26-09-2026', '10:00', 'AETOS HS1', 'Dynamo HS1', 'De Laar', '1', 'Oost', 'H1', 'ABC1', '', 'Arnhem']]);
    }

    private function service(): ImportService
    {
        return app(ImportService::class);
    }
}
