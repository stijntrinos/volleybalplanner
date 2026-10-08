<?php

namespace App\Support\Tenancy;

use App\Models\Club;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Context;

/**
 * Base for queued work that belongs to one club: it runs on that club's queue,
 * never overlaps with itself for the same club, and executes inside
 * CurrentClub::run() so BelongsToClub models are scoped correctly.
 */
abstract class ClubJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public Club $club)
    {
        $this->onQueue(ClubQueue::for($club));
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('club-'.$this->club->getKey().'-'.static::class))
                ->expireAfter($this->timeout)
                ->dontRelease(),
        ];
    }

    public function handle(CurrentClub $currentClub): void
    {
        Context::add('club_id', $this->club->getKey());

        // A read-only club keeps its planning as it was.
        if ($this->club->isReadOnly()) {
            return;
        }

        $currentClub->run($this->club, fn () => $this->handleForClub());
    }

    abstract protected function handleForClub(): void;
}
