<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The nightly sync reset the planner's court assignment to the team's default
 * court. This command restores it from the published version, which the sync
 * does not touch.
 */
class RepairCourtAssignmentTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $published;

    private ScheduleVersion $draft;

    /** @var array<int, int> */
    private array $courts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->club = $this->useClub($this->createClub());
        $this->published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
        $this->draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);

        $venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        foreach ([1, 2, 3] as $number) {
            $this->courts[$number] = $venue->courts()->create(['name' => (string) $number, 'sort_order' => $number])->id;
        }
    }

    public function test_the_court_assignment_comes_back_from_the_published_version(): void
    {
        $this->match($this->published, 'ABC-AB', $this->courts[3]);
        $overwritten = $this->match($this->draft, 'ABC-AB', $this->courts[1]);

        $this->repair('--apply --no-interaction')->assertSuccessful();

        $this->assertSame($this->courts[3], $overwritten->fresh()->court_id);
    }

    public function test_without_the_flag_nothing_changes(): void
    {
        $this->match($this->published, 'ABC-AB', $this->courts[3]);
        $overwritten = $this->match($this->draft, 'ABC-AB', $this->courts[1]);

        $this->repair('')->assertSuccessful();

        $this->assertSame($this->courts[1], $overwritten->fresh()->court_id);
    }

    /** A match the published version does not know was added later and keeps its court. */
    public function test_a_match_without_counterpart_is_left_alone(): void
    {
        $new = $this->match($this->draft, 'XYZ-CD', $this->courts[2]);

        $this->repair('--apply --no-interaction')->assertSuccessful();

        $this->assertSame($this->courts[2], $new->fresh()->court_id);
    }

    public function test_an_equal_assignment_gives_nothing_to_do(): void
    {
        $this->match($this->published, 'ABC-AB', $this->courts[3]);
        $this->match($this->draft, 'ABC-AB', $this->courts[3]);

        $this->repair('--apply --no-interaction')
            ->expectsOutputToContain('De veldindeling komt al overeen.')
            ->assertSuccessful();
    }

    public function test_a_source_that_does_not_exist_stops_with_a_message(): void
    {
        $this->repair('--from=9999 --no-interaction')->assertFailed();
    }

    public function test_a_version_of_another_club_cannot_be_used_as_source(): void
    {
        $foreign = app(CurrentClub::class)->run($this->createClub(), fn (): ScheduleVersion => ScheduleVersion::factory()->create([
            'status' => ScheduleVersionStatus::Published,
        ]));

        $this->repair("--from={$foreign->id} --no-interaction")->assertFailed();
    }

    private function repair(string $options): PendingCommand
    {
        return $this->artisan(trim("planner:repair-court-assignment --club={$this->club->slug} {$options}"));
    }

    private function match(ScheduleVersion $version, string $code, int $courtId): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => $code,
            'date' => '2026-09-12', 'time' => '14:30', 'court_id' => $courtId,
        ]);
    }
}
