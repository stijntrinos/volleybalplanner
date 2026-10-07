<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Filament\Pages\DutiesDashboard;
use App\Planner\Filament\Pages\DutyPlanner;
use App\Planner\Filament\Pages\Exports;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Planning screens open on the draft, viewing and export screens on the
 * published schedule (ported from VersieSelectieTest, page parts).
 */
class VersionSelectionPagesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $published;

    private ScheduleVersion $draft;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsClubUser();
        $this->published = ScheduleVersion::factory()->create(['name' => 'Seizoen 26-27', 'status' => ScheduleVersionStatus::Published]);
        $this->draft = ScheduleVersion::factory()->create(['name' => 'Werkversie', 'status' => ScheduleVersionStatus::Draft]);
    }

    public function test_planning_screens_open_on_the_draft(): void
    {
        foreach ([AwayMatches::class, DutyPlanner::class] as $page) {
            Livewire::test($page)->assertSet('versionId', $this->draft->id);
        }
    }

    public function test_viewing_screens_open_on_the_published_schedule(): void
    {
        foreach ([DutiesDashboard::class, Exports::class] as $page) {
            Livewire::test($page)->assertSet('versionId', $this->published->id);
        }
    }

    public function test_the_footer_text_can_be_overridden_once_without_changing_the_default(): void
    {
        PlannerSetting::current()->update(['schedule_footer' => 'STANDAARD']);

        Livewire::test(Exports::class)
            ->assertSet('footerText', 'STANDAARD')
            ->set('footerText', 'EENMALIG')
            ->call('resetFooterText')
            ->assertSet('footerText', 'STANDAARD');

        $this->assertSame('STANDAARD', PlannerSetting::current()->fresh()->schedule_footer);
    }

    public function test_the_select_list_shows_the_status_of_every_version(): void
    {
        $versions = Livewire::test(DutiesDashboard::class)->instance()->versions;

        $this->assertSame('Seizoen 26-27 · Gepubliceerd', $versions[$this->published->id]);
        $this->assertSame('Werkversie · Concept', $versions[$this->draft->id]);
    }
}
