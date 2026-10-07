<?php

namespace Tests\Unit\Planner;

use App\Planner\Features\PlannerFeature;
use Tests\TestCase;

class PlannerFeatureTest extends TestCase
{
    public function test_volunteer_points_and_volleystars_require_duties(): void
    {
        $this->assertSame([PlannerFeature::Duties], PlannerFeature::VolunteerPoints->requires());
        $this->assertSame([PlannerFeature::Duties], PlannerFeature::Volleystars->requires());
        $this->assertSame([], PlannerFeature::Trainings->requires());
    }

    public function test_unmet_requirements_lists_features_missing_a_dependency(): void
    {
        $this->assertSame([], PlannerFeature::unmetRequirements(['duties', 'volunteer_points']));
        $this->assertSame(
            ['Vrijwilligerspunten vereist Diensten (fluiten, tellen, zaaldienst).'],
            PlannerFeature::unmetRequirements(['volunteer_points', 'trainings']),
        );
    }

    public function test_every_feature_has_a_dutch_label(): void
    {
        foreach (PlannerFeature::cases() as $feature) {
            $this->assertStringNotContainsString('planner.', $feature->getLabel());
        }
    }
}
