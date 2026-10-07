<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Every page used to choose a version on its own, with three different
 * outcomes. Planning starts at the draft, viewing at the published schedule.
 * (Aetos VersieSelectieTest; the export footer part belongs to the Exports page.)
 */
class VersionSelectionTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const PLANNING_PAGES = [
        HomeMatches::class,
        'App\\Planner\\Filament\\Pages\\AwayMatches',
        'App\\Planner\\Filament\\Pages\\DutyPlanner',
    ];

    private const VIEWING_PAGES = [
        'App\\Planner\\Filament\\Pages\\DutiesDashboard',
        'App\\Planner\\Filament\\Pages\\Exports',
    ];

    private ScheduleVersion $published;

    private ScheduleVersion $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
        $this->published = ScheduleVersion::factory()->create([
            'name' => 'Seizoen 26-27', 'status' => ScheduleVersionStatus::Published, 'is_published' => true,
        ]);
        $this->draft = ScheduleVersion::factory()->create(['name' => 'Werkversie', 'status' => ScheduleVersionStatus::Draft]);
    }

    public function test_planning_screens_open_on_the_draft(): void
    {
        foreach ($this->existing(self::PLANNING_PAGES) as $page) {
            Livewire::test($page)->assertSet('versionId', $this->draft->id);
        }
    }

    public function test_viewing_screens_open_on_the_published_schedule(): void
    {
        $pages = $this->existing(self::VIEWING_PAGES);
        if ($pages === []) {
            $this->markTestSkipped('The viewing pages are not ported yet.');
        }

        foreach ($pages as $page) {
            Livewire::test($page)->assertSet('versionId', $this->published->id);
        }
    }

    public function test_the_select_list_shows_the_status_of_every_version(): void
    {
        $versions = Livewire::test(HomeMatches::class)->instance()->versions;

        $this->assertSame('Seizoen 26-27 · Gepubliceerd', $versions[$this->published->id]);
        $this->assertSame('Werkversie · Concept', $versions[$this->draft->id]);
    }

    /**
     * @param  list<string>  $pages
     * @return list<string>
     */
    private function existing(array $pages): array
    {
        return array_values(array_filter($pages, 'class_exists'));
    }
}
