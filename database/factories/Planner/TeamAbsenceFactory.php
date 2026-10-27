<?php

namespace Database\Factories\Planner;

use App\Planner\Models\Team;
use App\Planner\Models\TeamAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<TeamAbsence>
 */
class TeamAbsenceFactory extends Factory
{
    protected $model = TeamAbsence::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'date_from' => now()->addWeek()->toDateString(),
            'reason' => fake()->randomElement(['Toernooi', 'Kamp', 'Vakantie']),
        ];
    }
}
