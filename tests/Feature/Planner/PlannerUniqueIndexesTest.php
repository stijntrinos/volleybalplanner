<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PlannerUniqueIndexesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_a_team_may_train_on_several_courts_at_once(): void
    {
        $this->useClub($this->createClub());
        $venue = Venue::query()->create(['name' => 'Sportcentrum - Hal 5', 'city' => 'Arnhem']);
        $team = Team::query()->create(['code' => 'Recreanten', 'name' => 'Recreanten']);

        foreach ([1, 2, 3, 4] as $court) {
            Training::query()->create([
                'team_id' => $team->id,
                'venue_id' => $venue->id,
                'weekday' => 'donderdag',
                'start_time' => '19:30',
                'end_time' => '21:00',
                'court' => $court,
            ]);
        }

        $this->assertSame(4, Training::query()->count(), 'All four courts should exist as separate trainings.');
    }
}
