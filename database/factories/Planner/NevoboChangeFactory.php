<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\NevoboChangeField;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\NevoboChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<NevoboChange>
 */
class NevoboChangeFactory extends Factory
{
    protected $model = NevoboChange::class;

    public function definition(): array
    {
        return [
            'match_id' => FixtureMatch::factory(),
            'version_id' => fn (array $attributes): int => FixtureMatch::query()->findOrFail($attributes['match_id'])->version_id,
            'field' => NevoboChangeField::Time,
            'old_value' => '10:00',
            'new_value' => '12:30',
        ];
    }
}
