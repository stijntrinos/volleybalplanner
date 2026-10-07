<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Venue;
use App\Planner\Services\SittingVolleyballService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class SittingVolleyballServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_own_sitting_volleyball_team_is_home_only_in_a_venue_for_matches(): void
    {
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $service = app(SittingVolleyballService::class);
        $matchVenue = Venue::factory()->create(['for_matches' => true]);
        $otherVenue = Venue::factory()->create(['for_matches' => false]);

        $this->assertSame(
            ['match_type' => MatchType::Home, 'is_sitting_volleyball' => true],
            $service->classify('AETOS XZ 1', 'Orion XZ 1', $matchVenue, MatchType::Away),
        );
        $this->assertSame(
            ['match_type' => MatchType::Away, 'is_sitting_volleyball' => false],
            $service->classify('Orion XZ 1', 'AETOS XZ1', $otherVenue, MatchType::Home),
        );
        $this->assertSame(
            ['match_type' => MatchType::Home, 'is_sitting_volleyball' => false],
            $service->classify('AETOS XZ 10', 'Orion XZ 1', $matchVenue, MatchType::Home),
        );
    }

    public function test_without_the_module_the_default_type_applies(): void
    {
        $this->useClub($this->createClub(array_values(array_diff(PlannerFeature::allValues(), ['sitting_volleyball']))));
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);

        $this->assertSame(
            ['match_type' => MatchType::Away, 'is_sitting_volleyball' => false],
            app(SittingVolleyballService::class)->classify('AETOS XZ 1', 'Orion XZ 1', Venue::factory()->create(), MatchType::Away),
        );
    }
}
