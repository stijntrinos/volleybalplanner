<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => (string) fake()->unique()->numberBetween(1, 999),
        ];
    }
}
