<?php

namespace App\Planner\Support;

use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;

/**
 * One VolleyStars duty as the public overview shows it. A task gets its times
 * from the block it is in, and each role has other times; merging that
 * happens here so the page only has to show what is there.
 */
final class VolleystarsDuty
{
    public function __construct(
        public readonly string $date,
        public readonly string $role,
        public readonly ?string $team,
        public readonly ?string $teamName,
        public readonly ?string $start,
        public readonly ?string $end,
        public readonly ?string $venue,
        public readonly ?int $venueId,
        public readonly ?string $court,
        public readonly ?string $notes,
    ) {}

    public static function fromBlock(VolleystarsBlock $block, VolleystarsTask $task): self
    {
        $role = $task->role;

        [$start, $end] = $role->times($block);

        return new self(
            date: $block->date->toDateString(),
            role: $role->getLabel(),
            team: $task->team?->code,
            teamName: $task->team?->name,
            start: $start,
            end: $end,
            venue: $block->venue->name,
            venueId: $block->venue_id,
            court: $task->court?->name,
            notes: $block->notes,
        );
    }

    /** The month as "yyyy-mm", which the month picker groups on. */
    public function month(): string
    {
        return substr($this->date, 0, 7);
    }

    /** The text the search term is matched against. */
    public function searchText(): string
    {
        return collect([
            $this->role,
            $this->team,
            $this->teamName,
            $this->venue,
            $this->court,
        ])->filter()->implode(' ');
    }
}
