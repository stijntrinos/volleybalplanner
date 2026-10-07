<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Services\DutyOverviewImage;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutiesDashboardPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private array $tallies = [
        ['team' => 'Dames 2', 'used' => 100, 'earned' => 50, 'referee' => 10, 'scorer' => 20, 'hall_duty' => 10, 'volleystars' => 0, 'total' => 40, 'percentage' => 10.0],
        ['team' => 'Dames 1', 'used' => 100, 'earned' => 10, 'referee' => 40, 'scorer' => 10, 'hall_duty' => 20, 'volleystars' => 10, 'total' => 80, 'percentage' => 10.0],
        ['team' => 'Heren 1', 'used' => 100, 'earned' => 30, 'referee' => 20, 'scorer' => 0, 'hall_duty' => 10, 'volleystars' => 0, 'total' => 30, 'percentage' => 40.0],
    ];

    public function test_the_dashboard_calculates_the_distribution_over_teams(): void
    {
        $page = new DutiesDashboard;
        $page->tallies = $this->tallies;

        $this->assertSame([
            'teams' => 3,
            'average' => 50.0,
            'minimum' => 30,
            'maximum' => 80,
            'busiest_team' => 'Dames 1',
        ], $page->getSummaryProperty());
        $this->assertSame('team_asc', $page->sort);
    }

    public function test_the_dashboard_can_search_teams_and_sort_by_duty_type(): void
    {
        $page = new DutiesDashboard;
        $page->tallies = $this->tallies;
        $page->search = 'dames';
        $page->sort = 'referee_desc';

        $this->assertSame(['Dames 1', 'Dames 2'], array_column($page->getFilteredTalliesProperty(), 'team'));
    }

    public function test_the_dashboard_describes_the_load_against_the_target(): void
    {
        $page = new DutiesDashboard;

        $this->assertSame('12,5% tekort', $page->percentageLabel(12.5));
        $this->assertSame('8% boven doel', $page->percentageLabel(-8.0));
        $this->assertSame('Op doel', $page->percentageLabel(0.0));
    }

    public function test_the_period_is_described_in_words_above_the_image(): void
    {
        $page = new DutiesDashboard;
        $this->assertSame('Hele seizoen', $page->getPeriodLabelProperty());

        $page->toDate = '2026-12-12';
        $this->assertSame('Tot en met 12-12-2026', $page->getPeriodLabelProperty());

        $page->fromDate = '2026-09-03';
        $this->assertSame('03-09-2026 t/m 12-12-2026', $page->getPeriodLabelProperty());
    }

    public function test_the_image_shows_the_teams_the_totals_and_the_period(): void
    {
        $this->useClub($this->createClub());
        $page = new DutiesDashboard;
        $page->tallies = $this->tallies;

        $html = app(DutyOverviewImage::class)->html($page->imageRows(), $page->getTotalsProperty(), '03-09-2026 t/m 12-12-2026');

        $this->assertStringContainsString('03-09-2026 t/m 12-12-2026', $html);
        $this->assertStringContainsString('Dames 1', $html);
        $this->assertStringContainsString('Heren 1', $html);
        $this->assertStringContainsString('40% tekort', $html);
        // The total of all teams: 40 + 80 + 30.
        $this->assertMatchesRegularExpression('/<td class="total">150<\/td>/', $html);
    }

    public function test_rows_without_volunteer_points_get_an_empty_status_label(): void
    {
        $page = new DutiesDashboard;
        $page->tallies = [['team' => 'Dames 1', 'referee' => 1, 'scorer' => 0, 'hall_duty' => 0, 'total' => 1]];

        $this->assertSame('', $page->imageRows()[0]['status_label']);
        $this->assertSame(0, $page->getTotalsProperty()['used']);
    }

    public function test_point_and_volleystars_columns_follow_the_modules(): void
    {
        $this->seedTeamWithDuty([PlannerFeature::Duties->value]);

        Livewire::test(DutiesDashboard::class)
            ->assertOk()
            ->assertSee('HS 1')
            ->assertDontSee('Verbruikt')
            ->assertDontSee('Grootste tekort')
            ->assertDontSee('Meeste VolleyStars-taken');
    }

    public function test_with_every_module_the_point_and_volleystars_columns_show(): void
    {
        $this->seedTeamWithDuty(null);

        Livewire::test(DutiesDashboard::class)
            ->assertOk()
            ->assertSee('Verbruikt')
            ->assertSee('Grootste tekort')
            ->assertSee('Meeste VolleyStars-taken');
    }

    /** @param list<string>|null $features */
    private function seedTeamWithDuty(?array $features): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub($features));
        Team::factory()->create(['code' => 'HS 1', 'include_volunteers' => true]);
        $version = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create(['version_id' => $version->id, 'referee' => 'HS 1']);
    }
}
