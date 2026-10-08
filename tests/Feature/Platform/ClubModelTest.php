<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Models\User;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_belong_to_multiple_clubs(): void
    {
        $user = User::factory()->create();
        [$a, $b] = Club::factory()->count(2)->create();

        $user->clubs()->attach([$a->id, $b->id]);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $user->clubs()->pluck('clubs.id')->all());
        $this->assertTrue($a->users->contains($user));
    }

    public function test_club_uses_slug_as_route_key_and_gets_modules_from_its_plan(): void
    {
        $club = Club::factory()->withFeatures(['duties'])->create(['slug' => 'vv-test']);

        $this->assertSame('vv-test', $club->getRouteKey());
        $this->assertSame(['duties'], $club->fresh()->featureValues());
        $this->assertTrue($club->fresh()->is_active);
    }

    public function test_a_club_without_a_plan_has_only_the_core(): void
    {
        $club = Club::factory()->create();

        $this->assertSame([], $club->featureValues());
        $this->assertNull($club->planLimit(PlanLimit::Teams));
    }

    public function test_has_feature_requires_the_feature_and_its_dependencies(): void
    {
        $club = Club::factory()->withFeatures(['volunteer_points', 'trainings'])->create();

        $this->assertTrue($club->hasFeature(PlannerFeature::Trainings));
        $this->assertFalse($club->hasFeature(PlannerFeature::VolunteerPoints));
        $this->assertFalse($club->hasFeature(PlannerFeature::Duties));

        $club->plan->update(['features' => ['duties', 'volunteer_points']]);
        $this->assertTrue($club->hasFeature(PlannerFeature::VolunteerPoints));
    }

    public function test_unknown_feature_values_in_the_database_are_ignored(): void
    {
        $club = Club::factory()->withFeatures(['does_not_exist', 'duties'])->create();

        $this->assertTrue($club->hasFeature(PlannerFeature::Duties));
    }
}
