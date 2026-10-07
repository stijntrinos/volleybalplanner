<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\ClubJob;
use App\Support\Tenancy\ClubQueue;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Tests\TestCase;

class ProbeClubJob extends ClubJob
{
    public static ?int $seenClubId = null;

    protected function handleForClub(): void
    {
        self::$seenClubId = app(CurrentClub::class)->id();
    }
}

class ClubJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_name_follows_config(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-a']);

        config(['planner.queue.mode' => 'shared']);
        $this->assertSame('planner', ClubQueue::for($club));

        config(['planner.queue.mode' => 'per_club']);
        $this->assertSame('club-vv-a', ClubQueue::for($club));
    }

    public function test_job_runs_inside_its_club_and_restores_afterwards(): void
    {
        $club = Club::factory()->create();

        ProbeClubJob::dispatch($club);

        $this->assertSame($club->id, ProbeClubJob::$seenClubId);
        $this->assertNull(app(CurrentClub::class)->get());
    }

    public function test_job_is_put_on_the_club_queue_and_does_not_overlap(): void
    {
        config(['planner.queue.mode' => 'per_club']);
        $club = Club::factory()->create(['slug' => 'vv-b']);

        $job = new ProbeClubJob($club);

        $this->assertSame('club-vv-b', $job->queue);
        $this->assertInstanceOf(WithoutOverlapping::class, $job->middleware()[0]);
    }
}
