<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<VenueAlias>
 */
class VenueAliasFactory extends Factory
{
    protected $model = VenueAlias::class;

    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => 'Hal '.fake()->unique()->bothify('??-###'),
        ];
    }
}
