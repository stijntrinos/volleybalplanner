<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Models\Team;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<VolleystarsTask>
 */
class VolleystarsTaskFactory extends Factory
{
    protected $model = VolleystarsTask::class;

    public function definition(): array
    {
        return [
            'block_id' => VolleystarsBlock::factory(),
            'role' => VolleystarsRole::Scoring,
            'team_id' => Team::factory(),
        ];
    }
}
