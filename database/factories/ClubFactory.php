<?php

namespace Database\Factories;

use App\Models\Club;
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
            'features' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /** @param  list<string>  $features */
    public function withFeatures(array $features): static
    {
        return $this->state(['features' => $features]);
    }
}
