<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Services\Duties\PointsBalance;
use App\Planner\Services\DutyOverviewImage;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutiesDashboardPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private array $tallies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tallies = [
            $this->row('Dames 2', need: 100, earned: 70, referee: 10, scorer: 20, hallDuty: 10),
            $this->row('Dames 1', need: 100, earned: 10, referee: 40, scorer: 10, hallDuty: 20, volleystars: 10),
            $this->row('Heren 1', need: 100, earned: 30, referee: 20, scorer: 0, hallDuty: 10),
        ];
    }

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
            'below_minimum' => 2,
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

    public function test_the_dashboard_describes_the_standing_against_the_minimum(): void
    {
        $this->useClub($this->createClub());
        $page = new DutiesDashboard;

        $this->assertSame('90,0%', $page->statusLabel($this->tallies[1]));
        $this->assertSame('110,0% boven doel', $page->statusLabel($this->tallies[0]));
        $this->assertSame('-10', $page->remainingLabel($this->tallies[0]));
        $this->assertSame('10', $page->remainingLabel($this->tallies[1]));
        $this->assertSame('Geen minimum', $page->statusLabel($this->row('Heren 2', need: 0, earned: 0, referee: 1, scorer: 0, hallDuty: 0)));
    }

    public function test_the_shortfall_is_compared_with_another_version(): void
    {
        $this->actingAsClubUser();
        Team::factory()->create(['code' => 'HS 1', 'volunteer_points_used' => 10]);
        $shown = ScheduleVersion::factory()->create();
        $other = ScheduleVersion::factory()->create();
        FixtureMatch::factory()->create(['version_id' => $shown->id, 'scorer' => 'HS 1', 'scorer_points' => 4]);
        FixtureMatch::factory()->create(['version_id' => $other->id, 'scorer' => 'HS 1', 'scorer_points' => 1]);

        $component = Livewire::test(DutiesDashboard::class)
            ->set('versionId', $shown->id)
            ->set('compareVersionId', $other->id);

        $this->assertSame(['HS 1' => 9], $component->get('comparison'));
        $this->assertSame('+3', $component->instance()->differenceLabel($component->get('tallies')[0]));
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
        $this->assertStringContainsString('60,0%', $html);
        // The total of all teams: 110 + 90 + 60.
        $this->assertMatchesRegularExpression('/<td class="total">260<\/td>/', $html);
    }

    public function test_rows_without_volunteer_points_get_an_empty_status_label(): void
    {
        $this->useClub($this->createClub([PlannerFeature::Duties->value]));
        $page = new DutiesDashboard;
        $page->tallies = [$this->row('Dames 1', need: 0, earned: 0, referee: 1, scorer: 0, hallDuty: 0)];

        $this->assertSame('', $page->imageRows()[0]['status_label']);
        $this->assertSame(0, $page->getTotalsProperty()['minimum']);
    }

    public function test_point_and_volleystars_columns_follow_the_modules(): void
    {
        $this->seedTeamWithDuty([PlannerFeature::Duties->value]);

        Livewire::test(DutiesDashboard::class)
            ->assertOk()
            ->assertSee('HS 1')
            ->assertDontSee('Nog in te vullen')
            ->assertDontSee('Laagste percentage')
            ->assertDontSee('Meeste VolleyStars-taken');
    }

    public function test_with_every_module_the_point_and_volleystars_columns_show(): void
    {
        $this->seedTeamWithDuty(null);

        Livewire::test(DutiesDashboard::class)
            ->assertOk()
            ->assertSee('Nog in te vullen')
            ->assertSee('Laagste percentage')
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

    private function row(string $team, int $need, int $earned, int $referee, int $scorer, int $hallDuty, int $volleystars = 0): array
    {
        $duties = ['referee' => $referee, 'scorer' => $scorer, 'hall_duty' => $hallDuty, 'volleystars' => $volleystars];

        return ['team' => $team] + (new PointsBalance($need, null, $earned, array_sum($duties)))->toArray() + $duties;
    }
}
