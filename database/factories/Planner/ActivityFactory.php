<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Activity;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'version_id' => ScheduleVersion::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+20 weeks')->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'title' => fake()->words(2, true),
        ];
    }
}
