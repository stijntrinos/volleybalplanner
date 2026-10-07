<?php

namespace Database\Factories\Planner;

use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<HallDuty>
 */
class HallDutyFactory extends Factory
{
    protected $model = HallDuty::class;

    public function definition(): array
    {
        return [
            'version_id' => ScheduleVersion::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+20 weeks')->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'team_id' => Team::factory(),
            'venue_id' => Venue::factory(),
        ];
    }
}
