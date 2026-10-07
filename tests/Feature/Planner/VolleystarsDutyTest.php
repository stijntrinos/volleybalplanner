<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Exceptions\PublishBlocked;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Planner\Support\VolleystarsDuty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class VolleystarsDutyTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_a_task_takes_the_times_of_its_role_from_the_block(): void
    {
        $this->useClub($this->createClub());
        $block = VolleystarsBlock::factory()->create([
            'date' => '2026-10-03', 'venue_id' => Venue::factory()->create(['name' => 'De Laar'])->id,
            'setup_start' => '08:30', 'setup_end' => '09:00', 'scoring_start' => '09:00', 'scoring_end' => '12:00',
            'teardown_start' => '12:00', 'teardown_end' => '12:30', 'notes' => 'Ingang achterzijde',
        ]);
        $task = VolleystarsTask::factory()->create([
            'block_id' => $block->id, 'role' => VolleystarsRole::Scoring,
            'team_id' => Team::factory()->create(['code' => 'DS 2', 'name' => 'Club DS 2'])->id,
        ]);

        $duty = VolleystarsDuty::fromBlock($block, $task);

        $this->assertSame('2026-10-03', $duty->date);
        $this->assertSame('Tellen', $duty->role);
        $this->assertSame(['09:00', '12:00'], [substr($duty->start, 0, 5), substr($duty->end, 0, 5)]);
        $this->assertSame('De Laar', $duty->venue);
        $this->assertSame('2026-10', $duty->month());
        $this->assertSame('Tellen DS 2 Club DS 2 De Laar Ingang achterzijde', $duty->searchText());
    }

    public function test_publish_blocked_carries_the_conflicts(): void
    {
        $exception = new PublishBlocked([['level' => 'conflict'], ['level' => 'conflict']]);

        $this->assertCount(2, $exception->conflicts);
        $this->assertSame('Publiceren is geblokkeerd omdat de planning 2 harde conflicten bevat.', $exception->getMessage());
        $this->assertStringEndsWith('1 harde conflict bevat.', (new PublishBlocked([[]]))->getMessage());
    }
}
