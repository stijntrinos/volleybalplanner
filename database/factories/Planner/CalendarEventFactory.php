<?php

namespace Database\Factories\Planner;

use App\Planner\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        return [
            'start_date' => $start = fake()->dateTimeBetween('+1 week', '+20 weeks')->format('Y-m-d'),
            'end_date' => $start,
            'name' => fake()->unique()->words(2, true),
            'type' => 'Vakantie',
        ];
    }
}
