<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\Exports;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ExportsPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_page_selects_the_next_match_day_and_its_venue(): void
    {
        $this->actingAsClubUser();
        PlannerSetting::current()->update(['schedule_footer' => 'Meld je bij de zaalwacht']);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        $day = CarbonImmutable::now()->addWeek()->toDateString();
        FixtureMatch::factory()->create(['version_id' => $version->id, 'venue_id' => $venue->id, 'date' => $day, 'match_type' => MatchType::Home]);

        Livewire::test(Exports::class)
            ->assertOk()
            ->assertSet('versionId', $version->id)
            ->assertSet('date', $day)
            ->assertSet('venueId', $venue->id)
            ->assertSet('footerText', 'Meld je bij de zaalwacht')
            ->assertSee('De Laar')
            ->assertSee('FTZ-taken per wedstrijddag');
    }

    public function test_the_footer_text_is_required_for_the_day_schedule(): void
    {
        $this->actingAsClubUser();
        ScheduleVersion::factory()->create();

        Livewire::test(Exports::class)
            ->set('footerText', '   ')
            ->call('daySchedulePdf')
            ->assertHasErrors(['footerText' => 'required'])
            ->call('resetFooterText')
            ->assertHasNoErrors();
    }

    public function test_the_duties_export_needs_the_duties_module(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub([PlannerFeature::AwayMatches->value]));
        ScheduleVersion::factory()->create();

        Livewire::test(Exports::class)
            ->assertDontSee('FTZ-taken per wedstrijddag')
            ->call('dutiesExcel')
            ->assertForbidden();
    }

    public function test_the_conflicts_report_downloads(): void
    {
        $this->actingAsClubUser();
        ScheduleVersion::factory()->create();

        Livewire::test(Exports::class)
            ->call('conflictsExcel')
            ->assertFileDownloaded('conflict-report.xlsx');
    }
}
