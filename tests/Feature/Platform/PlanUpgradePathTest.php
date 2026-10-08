<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\Plan;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanUpgradePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_upgrade_is_the_next_plan_that_offers_the_module(): void
    {
        $club = Club::factory()->onPlan('basis')->create();

        $this->assertSame('Vereniging', $club->upgradePlanName(PlannerFeature::ChangeRequests));
        $this->assertSame('Groot', $club->upgradePlanName(PlannerFeature::SittingVolleyball));
    }

    public function test_the_upgrade_for_a_limit_has_room_for_one_more(): void
    {
        $club = Club::factory()->onPlan('basis')->create();

        $this->assertSame('Vereniging', $club->upgradePlanName(PlanLimit::Teams, 16));
        $this->assertSame('Groot', $club->upgradePlanName(PlanLimit::Teams, 31));
        $this->assertFalse($club->allowsAnother(PlanLimit::Venues, 1));
        $this->assertTrue($club->allowsAnother(PlanLimit::Venues, 0));
    }

    public function test_the_largest_plan_has_no_upgrade(): void
    {
        $club = Club::factory()->onPlan('groot')->create();

        $this->assertNull($club->upgradePlanName(PlannerFeature::SittingVolleyball));
        $this->assertTrue($club->allowsAnother(PlanLimit::Teams, 500));
    }

    public function test_a_club_without_a_plan_is_offered_the_first_plan_that_fits(): void
    {
        $club = Club::factory()->create();

        $this->assertSame('Basis', $club->upgradePlanName(PlannerFeature::Duties));
        $this->assertTrue(Plan::query()->where('slug', 'basis')->sole()->is(Plan::firstWith(PlannerFeature::Trainings)));
    }
}
