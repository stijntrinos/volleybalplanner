<?php

namespace Database\Factories\Planner;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Models\ChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * club_id is filled by BelongsToClub from the current club; related records
 * are created in that same club.
 *
 * @extends Factory<ChangeRequest>
 */
class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    public function definition(): array
    {
        return [
            'kind' => ChangeRequestKind::Match,
            'match_code' => strtoupper(fake()->bothify('??#?#-??')),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'reason' => 'Vakantie of schoolactiviteit',
            'explanation' => fake()->sentence(),
            'status' => ChangeRequestStatus::Open,
        ];
    }
}
