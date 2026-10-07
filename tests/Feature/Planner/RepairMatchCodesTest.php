<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class RepairMatchCodesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->club = $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
    }

    public function test_the_copy_disappears_and_the_federation_match_stays(): void
    {
        $federation = $this->match('BD1E16-BC');
        $copy = $this->match('5000BD1E16BC');

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($federation);
        $this->assertDatabaseMissing('planner_matches', ['id' => $copy->id]);
    }

    public function test_padding_spaces_that_became_a_dash_are_recognised_too(): void
    {
        $federation = $this->match('3CD-AD');
        $copy = $this->match('50003CD-AD');

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($federation);
        $this->assertDatabaseMissing('planner_matches', ['id' => $copy->id]);
    }

    public function test_a_match_only_the_sync_knows_stays_with_a_repaired_code(): void
    {
        $only = $this->match('5000JBHA1-BA-1');

        $this->repair('--apply --no-interaction');

        $this->assertSame('JBHA1-BA-2', $only->fresh()->match_code);
    }

    public function test_a_copy_with_a_manually_assigned_referee_stays(): void
    {
        $this->match('BD1E16-BC');
        $copy = $this->match('5000BD1E16BC', ['referee' => 'HS 3', 'referee_auto' => false]);

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($copy);
    }

    public function test_an_automatically_filled_scorer_does_not_keep_the_copy(): void
    {
        $this->match('BD1E16-BC');
        $copy = $this->match('5000BD1E16BC', ['scorer' => 'MB 4', 'scorer_auto' => true]);

        $this->repair('--apply --no-interaction');

        $this->assertDatabaseMissing('planner_matches', ['id' => $copy->id]);
    }

    public function test_without_the_flag_nothing_changes(): void
    {
        $this->match('BD1E16-BC');
        $copy = $this->match('5000BD1E16BC');

        $this->repair('');

        $this->assertModelExists($copy);
        $this->assertSame(2, FixtureMatch::query()->count());
    }

    public function test_a_copy_in_another_version_does_not_count_as_duplicate(): void
    {
        $other = ScheduleVersion::factory()->create();
        $this->match('BD1E16-BC');
        $loose = $this->match('5000BD1E16BC', ['version_id' => $other->id]);

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($loose);
        $this->assertSame('BD1E16-BC', $loose->fresh()->match_code);
    }

    public function test_ordinary_federation_codes_are_left_alone(): void
    {
        $normal = $this->match('JBHA1-BA');
        $synthetic = $this->match('RSS-AETOSJB1-DYNAMOJB1');

        $this->repair('');

        $this->assertSame('JBHA1-BA', $normal->fresh()->match_code);
        $this->assertSame('RSS-AETOSJB1-DYNAMOJB1', $synthetic->fresh()->match_code);
    }

    /** Duplicates without a district number, only a wrong sequence number, are recognised by the match itself. */
    public function test_the_same_match_under_two_codes_keeps_the_oldest(): void
    {
        $fromPlanning = $this->match('JBHA1-BA-2', ['created_at' => '2026-09-04 08:52:00']);
        $fromSync = $this->match('JBHA1-BA-1', ['created_at' => '2026-09-12 06:00:00']);

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($fromPlanning);
        $this->assertDatabaseMissing('planner_matches', ['id' => $fromSync->id]);
    }

    public function test_two_matches_at_another_time_both_stay(): void
    {
        $early = $this->match('JBHA1-BA', ['time' => '10:45']);
        $late = $this->match('JBHA1-BA-2', ['time' => '12:30']);

        $this->repair('--apply --no-interaction');

        $this->assertModelExists($early);
        $this->assertModelExists($late);
    }

    public function test_another_club_is_not_touched(): void
    {
        $other = $this->createClub();
        [$otherFederation, $otherCopy] = app(CurrentClub::class)->run($other, function (): array {
            $version = ScheduleVersion::factory()->create();

            return [$this->match('BD1E16-BC', ['version_id' => $version->id]), $this->match('5000BD1E16BC', ['version_id' => $version->id])];
        });
        $copy = $this->match('5000BD1E16BC');
        $this->match('BD1E16-BC');

        $this->repair('--apply --no-interaction');

        $this->assertDatabaseMissing('planner_matches', ['id' => $copy->id]);
        $this->assertDatabaseHas('planner_matches', ['id' => $otherCopy->id, 'match_code' => '5000BD1E16BC']);
        $this->assertDatabaseHas('planner_matches', ['id' => $otherFederation->id]);
    }

    public function test_club_or_all_is_required(): void
    {
        $this->artisan('planner:repair-match-codes')->assertFailed();
        $this->artisan('planner:repair-match-codes --club=bestaat-niet')->assertFailed();
        $this->artisan("planner:repair-match-codes --all --club={$this->club->slug}")->assertFailed();
        $this->artisan('planner:repair-match-codes --all')->assertSuccessful();
    }

    private function repair(string $options): void
    {
        $this->artisan(trim("planner:repair-match-codes --club={$this->club->slug} {$options}"))->assertSuccessful();
    }

    /** @param  array<string, mixed>  $extra */
    private function match(string $code, array $extra = []): FixtureMatch
    {
        return FixtureMatch::factory()->create($extra + [
            'version_id' => $this->version->id, 'match_code' => $code, 'date' => '2026-09-12', 'time' => '12:30',
            'home_team_name' => 'AETOS JB 1', 'away_team_name' => 'SV Dynamo Apeldoorn JB 1',
        ]);
    }
}
