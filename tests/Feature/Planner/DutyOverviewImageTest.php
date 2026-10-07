<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\PlannerSetting;
use App\Planner\Services\DutyOverviewImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DutyOverviewImageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    /** Rows as DutyTallyService::perTeam() returns them with volunteer points, plus the status label. */
    private array $rows = [
        ['team' => 'Dames 1', 'used' => 100, 'earned' => 10, 'referee' => 40, 'scorer' => 10, 'hall_duty' => 20, 'volleystars' => 10, 'total' => 80, 'percentage' => 10.0, 'status_label' => '10% tekort'],
        ['team' => 'Dames 2', 'used' => 100, 'earned' => 50, 'referee' => 10, 'scorer' => 20, 'hall_duty' => 10, 'volleystars' => 0, 'total' => 40, 'percentage' => 10.0, 'status_label' => '10% tekort'],
        ['team' => 'Heren 1', 'used' => 100, 'earned' => 30, 'referee' => 20, 'scorer' => 0, 'hall_duty' => 10, 'volleystars' => 0, 'total' => 30, 'percentage' => 40.0, 'status_label' => '40% tekort'],
    ];

    private array $totals = ['referee' => 70, 'scorer' => 30, 'hall_duty' => 40, 'volleystars' => 10, 'total' => 150];

    public function test_the_image_shows_the_teams_the_totals_and_the_period(): void
    {
        $club = $this->useClub($this->createClub());
        $club->update(['name' => 'VV Demo']);

        $html = app(DutyOverviewImage::class)->html($this->rows, $this->totals, '03-09-2026 t/m 12-12-2026');

        $this->assertStringContainsString('03-09-2026 t/m 12-12-2026', $html);
        $this->assertStringContainsString('Dames 1', $html);
        $this->assertStringContainsString('Heren 1', $html);
        $this->assertStringContainsString('40% tekort', $html);
        $this->assertStringContainsString('VolleyStars', $html);
        // The total of all teams: 80 + 40 + 30.
        $this->assertMatchesRegularExpression('/<td class="total">150<\/td>/', $html);
        $this->assertStringContainsString('VV Demo', $html);
        $this->assertStringNotContainsStringIgnoringCase('aetos', $html);
    }

    public function test_the_club_colour_is_used(): void
    {
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['primary_color' => '#112233']);

        $html = app(DutyOverviewImage::class)->html($this->rows, $this->totals, 'Hele seizoen');

        $this->assertStringContainsString('--planner-primary: #112233;', $html);
    }

    public function test_volleystars_and_status_columns_follow_the_modules(): void
    {
        $this->useClub($this->createClub(['duties']));

        $html = app(DutyOverviewImage::class)->html($this->rows, $this->totals, 'Hele seizoen');

        $this->assertStringNotContainsString('VolleyStars', $html);
        $this->assertStringNotContainsString('40% tekort', $html);
        $this->assertStringContainsString('Aantal diensten per team', $html);
    }
}
