<?php

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageView> */
class PageViewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'path' => '/',
            'visit_hash' => hash('sha256', fake()->uuid()),
            'referrer_host' => null,
            'device_type' => 'desktop',
            'viewed_at' => now(),
        ];
    }
}
