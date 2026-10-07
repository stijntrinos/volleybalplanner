<?php

namespace Database\Factories\Planner;

use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<VolleystarsBlock>
 */
class VolleystarsBlockFactory extends Factory
{
    protected $model = VolleystarsBlock::class;

    public function definition(): array
    {
        return [
            'version_id' => ScheduleVersion::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+20 weeks')->format('Y-m-d'),
            'venue_id' => Venue::factory(),
        ];
    }
}
