<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<FixtureMatch>
 */
class FixtureMatchFactory extends Factory
{
    protected $model = FixtureMatch::class;

    public function definition(): array
    {
        return [
            'version_id' => ScheduleVersion::factory(),
            'date' => fake()->dateTimeBetween('+1 week', '+20 weeks')->format('Y-m-d'),
            'time' => fake()->randomElement(['10:00', '12:30', '15:00', '17:00']),
            'duration_minutes' => 105,
            'home_team_name' => 'VV Demo HS 1',
            'away_team_name' => fake()->company().' HS 1',
            'match_code' => strtoupper(fake()->unique()->bothify('??####-??')),
            'match_type' => MatchType::Home,
        ];
    }
}
