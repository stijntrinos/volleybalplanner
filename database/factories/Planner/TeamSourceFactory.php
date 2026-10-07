<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\TeamSourceType;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<TeamSource>
 */
class TeamSourceFactory extends Factory
{
    protected $model = TeamSource::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Competitie '.fake()->unique()->bothify('??#'),
            'url' => fake()->url(),
            'type' => TeamSourceType::Schedule,
        ];
    }
}
