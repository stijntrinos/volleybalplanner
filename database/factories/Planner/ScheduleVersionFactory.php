<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<ScheduleVersion>
 */
class ScheduleVersionFactory extends Factory
{
    protected $model = ScheduleVersion::class;

    public function definition(): array
    {
        return [
            'name' => 'Concept '.fake()->unique()->word(),
            'status' => ScheduleVersionStatus::Draft,
        ];
    }
}
