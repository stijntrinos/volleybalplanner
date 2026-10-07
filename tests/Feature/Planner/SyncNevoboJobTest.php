<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Jobs\SyncNevoboPlanningJob;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The nightly Nevobo sync runs as one job per active club, each inside its own
 * club, and never writes into another club's planning.
 */
class SyncNevoboJobTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_command_dispatches_one_job_per_active_club(): void
    {
        Queue::fake();
        $first = $this->createClub();
        $second = $this->createClub();
        $inactive = $this->createClub();
        $inactive->update(['is_active' => false]);

        $this->artisan('planner:sync-nevobo --all')->assertSuccessful();

        Queue::assertPushed(SyncNevoboPlanningJob::class, 2);
        Queue::assertPushed(SyncNevoboPlanningJob::class, fn (SyncNevoboPlanningJob $job): bool => $job->club->is($first));
        Queue::assertPushed(SyncNevoboPlanningJob::class, fn (SyncNevoboPlanningJob $job): bool => $job->club->is($second));
        Queue::assertNotPushed(SyncNevoboPlanningJob::class, fn (SyncNevoboPlanningJob $job): bool => $job->club->is($inactive));
    }

    public function test_the_command_can_target_one_club(): void
    {
        Queue::fake();
        $club = $this->createClub();
        $this->createClub();

        $this->artisan("planner:sync-nevobo --club={$club->slug}")->assertSuccessful();

        Queue::assertPushed(SyncNevoboPlanningJob::class, 1);
        Queue::assertPushed(SyncNevoboPlanningJob::class, fn (SyncNevoboPlanningJob $job): bool => $job->club->is($club));
    }

    public function test_the_sync_is_scheduled_daily_at_six(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'planner:sync-nevobo --all'));

        $this->assertNotNull($event, 'planner:sync-nevobo --all should be scheduled');
        $this->assertSame('0 6 * * *', $event->expression);
    }

    public function test_each_job_only_syncs_its_own_club(): void
    {
        $alpha = $this->clubWithFeed('https://alpha.test/feed.xml', 'HS 1');
        $beta = $this->clubWithFeed('https://beta.test/feed.xml', 'DS 2');
        Http::fake([
            'alpha.test/*' => Http::response($this->feed('AETOS HS 1 - Dynamo HS 1 (ALPHA1)')),
            'beta.test/*' => Http::response($this->feed('AETOS DS 2 - Orion DS 2 (BETA1)')),
        ]);

        $this->runJob($alpha);

        $alphaCodes = $this->codes($alpha);
        $this->assertSame(['ALPHA1'], $alphaCodes);
        $this->assertSame([], $this->codes($beta), 'the other club is not synced by this job');
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'beta.test'));

        $this->runJob($beta);

        $this->assertSame(['BETA1'], $this->codes($beta));
        $this->assertSame(['ALPHA1'], $this->codes($alpha));
    }

    public function test_a_club_without_draft_version_is_skipped(): void
    {
        $club = $this->createClub();
        Http::fake();

        $job = $this->runJob($club);

        $this->assertNull($job->result);
        Http::assertNothingSent();
    }

    private function runJob(Club $club): SyncNevoboPlanningJob
    {
        $job = new SyncNevoboPlanningJob($club);
        $job->handle(app(CurrentClub::class));

        return $job;
    }

    private function clubWithFeed(string $url, string $code): Club
    {
        $club = $this->createClub();
        app(CurrentClub::class)->run($club, function () use ($url, $code): void {
            PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
            ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
            $team = Team::factory()->create(['code' => $code]);
            TeamSource::factory()->create(['team_id' => $team->id, 'url' => $url, 'is_active' => true]);
        });

        return $club;
    }

    /** @return list<string> */
    private function codes(Club $club): array
    {
        return app(CurrentClub::class)->run($club, fn (): array => FixtureMatch::query()->orderBy('match_code')->pluck('match_code')->all());
    }

    private function feed(string $title): string
    {
        return '<?xml version="1.0"?><rss><channel><item>'
            ."<title>{$title}</title>"
            .'<description>Datum 12-09-2026 10:00 | Poule: H1 | Locatie: De Laar</description>'
            .'</item></channel></rss>';
    }
}
