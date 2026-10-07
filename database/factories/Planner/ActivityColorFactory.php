<?php

namespace Database\Factories\Planner;

use App\Planner\Models\ActivityColor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<ActivityColor>
 */
class ActivityColorFactory extends Factory
{
    protected $model = ActivityColor::class;

    public function definition(): array
    {
        return [
            'type' => fake()->unique()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
