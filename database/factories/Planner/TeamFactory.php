<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'code' => $code = strtoupper(fake()->unique()->bothify('??#')),
            'name' => "VV Demo {$code}",
            'plays_on_two_courts' => false,
            'scores_own_match' => false,
        ];
    }
}
