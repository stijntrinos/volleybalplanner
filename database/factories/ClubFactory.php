<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    public function definition(): array
    {
        $name = 'VV '.fake()->unique()->city();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'is_active' => true,
            'plan_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * A club on its own plan with exactly these modules and no limits.
     *
     * @param  list<string>  $features
     */
    public function withFeatures(array $features): static
    {
        return $this->state(['plan_id' => Plan::factory()->withFeatures($features)]);
    }

    public function onPlan(Plan|string $plan): static
    {
        return $this->state(['plan_id' => $plan instanceof Plan ? $plan->id : Plan::query()->where('slug', $plan)->value('id')]);
    }
}
