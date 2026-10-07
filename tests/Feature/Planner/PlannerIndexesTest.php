<?php

namespace Tests\Feature\Planner;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlannerIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_day_planner_query_has_an_index(): void
    {
        $columns = collect(Schema::getIndexes('planner_matches'))->pluck('columns');

        $this->assertTrue(
            $columns->contains(fn (array $index): bool => array_slice($index, 0, 2) === ['version_id', 'date']),
            'planner_matches is missing an index on (version_id, date)',
        );
    }
}
