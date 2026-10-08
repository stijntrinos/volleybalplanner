<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Pakket '.Str::upper(Str::random(4));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'tagline' => null,
            'price' => null,
            'max_teams' => null,
            'max_venues' => null,
            'max_admins' => null,
            'features' => [],
            'highlights' => [],
            'is_featured' => false,
            'is_public' => false,
            'sort_order' => 100,
        ];
    }

    /** @param  list<string>  $features */
    public function withFeatures(array $features): static
    {
        return $this->state(['features' => $features]);
    }
}
