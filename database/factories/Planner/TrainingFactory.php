<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Support\Weekday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Training>
 */
class TrainingFactory extends Factory
{
    protected $model = Training::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'venue_id' => Venue::factory(),
            'weekday' => fake()->randomElement(Weekday::NAMES),
            'start_time' => '19:30',
            'end_time' => '21:00',
            'court' => fake()->numberBetween(1, 4),
        ];
    }
}
