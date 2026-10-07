<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    protected $model = Availability::class;

    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'scope' => AvailabilityScope::Weekday,
            'weekday' => 6,
            'status' => AvailabilityStatus::Available,
        ];
    }
}
