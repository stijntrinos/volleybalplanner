<?php

namespace Tests;

use App\Planner\Support\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No DNS in tests: feed hosts resolve to nothing unless a test says so.
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function ipv4(string $host): array
            {
                return [];
            }
        });
    }
}
