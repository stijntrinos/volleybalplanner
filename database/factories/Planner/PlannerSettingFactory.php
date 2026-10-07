<?php

namespace Database\Factories\Planner;

use App\Planner\Models\PlannerSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<PlannerSetting>
 */
class PlannerSettingFactory extends Factory
{
    protected $model = PlannerSetting::class;

    public function definition(): array
    {
        return [
            'team_name_patterns' => [],
        ];
    }
}
