<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        return [
            'venue_code' => strtoupper(fake()->unique()->bothify('??##')),
            'name' => 'Sporthal '.fake()->unique()->lastName(),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'is_managed' => false,
            'for_matches' => true,
            'for_trainings' => true,
            'is_active' => true,
        ];
    }
}
