<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Filament\Pages\Exports;
use App\Planner\Filament\Pages\ImportPlanning;
use App\Planner\Filament\Pages\NevoboChanges;
use App\Planner\Filament\Pages\Settings;
use App\Planner\Filament\Pages\VenueOccupancy;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Pages of an optional module disappear from the navigation and refuse a
 * direct URL when the club has the module switched off.
 */
class PlannerPageModulesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    /** @return array<string, array{class-string, string, PlannerFeature}> */
    public static function modulePages(): array
    {
        return [
            'away matches' => [AwayMatches::class, 'away-matches', PlannerFeature::AwayMatches],
            'duty planner' => [DutyPlanner::class, 'duty-planner', PlannerFeature::Duties],
            'duties dashboard' => [DutiesDashboard::class, 'duties-dashboard', PlannerFeature::Duties],
        ];
    }

    /** @return array<string, array{class-string, string}> */
    public static function corePages(): array
    {
        return [
            'import' => [ImportPlanning::class, 'import-planning'],
            'nevobo changes' => [NevoboChanges::class, 'nevobo-changes'],
            'settings' => [Settings::class, 'settings'],
            'venue occupancy' => [VenueOccupancy::class, 'venue-occupancy'],
            'exports' => [Exports::class, 'exports'],
        ];
    }

    /** @param class-string $page */
    #[DataProvider('modulePages')]
    public function test_a_module_page_is_hidden_and_refused_without_its_module(string $page, string $slug, PlannerFeature $feature): void
    {
        $others = array_values(array_diff(PlannerFeature::allValues(), [$feature->value, ...$this->dependents($feature)]));
        $club = $this->createClub($others);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->assertFalse($page::canAccess());
        $this->assertFalse($page::shouldRegisterNavigation());
        $this->get('/app/'.$club->slug.'/'.$slug)->assertForbidden();
    }

    /** @param class-string $page */
    #[DataProvider('modulePages')]
    public function test_a_module_page_opens_with_its_module(string $page, string $slug, PlannerFeature $feature): void
    {
        $club = $this->createClub([$feature->value]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->assertTrue($page::canAccess());
        $this->assertTrue($page::shouldRegisterNavigation());
        $this->get('/app/'.$club->slug.'/'.$slug)->assertOk();
    }

    /** @param class-string $page */
    #[DataProvider('corePages')]
    public function test_core_pages_open_without_any_module(string $page, string $slug): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->assertTrue($page::canAccess());
        $this->get('/app/'.$club->slug.'/'.$slug)->assertOk();
    }

    /** @return list<string> features that require the given one */
    private function dependents(PlannerFeature $feature): array
    {
        return array_values(array_map(
            fn (PlannerFeature $other): string => $other->value,
            array_filter(PlannerFeature::cases(), fn (PlannerFeature $other): bool => in_array($feature, $other->requires(), true)),
        ));
    }
}
