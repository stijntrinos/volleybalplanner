<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\MatchType;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The page parts of the venue selection: a search result opens the venue of
 * that match, and the date may be typed or come from the calendar.
 * (UI parts of Aetos PlannerZaalkeuzeTest)
 */
class HomeMatchesNavigationTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $main;

    private Venue $second;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
        $this->second = Venue::factory()->create(['name' => 'De Laar', 'is_active' => true, 'for_matches' => true]);
        $this->main = Venue::factory()->create(['name' => 'Middachtensingel', 'is_active' => true, 'for_matches' => true]);
        $this->version = ScheduleVersion::factory()->create();
        PlannerSetting::current()->update(['main_venue_id' => $this->main->id]);
    }

    public function test_a_search_result_opens_the_venue_of_that_match(): void
    {
        $this->match($this->main, 'W1');
        $this->match($this->second, 'W2');
        $searched = FixtureMatch::query()->where('match_code', 'W2')->firstOrFail();

        $page = $this->page()
            ->set('date', '2026-09-12')
            ->call('load')
            ->assertSet('hiddenVenues', [$this->second->id])
            ->set('search', 'W2');

        $this->assertSame([$searched->id], array_column($page->instance()->searchResults, 'id'));

        $page->call('selectSearchResult', $searched->id)
            ->assertDispatched('planner-search-result-selected', matchId: $searched->id);

        $this->assertNotContains($this->second->id, $page->get('hiddenVenues'), 'The venue of the search result should be open.');
    }

    /**
     * The date field is typed as dd-MM-yyyy, but the browser calendar button
     * gives yyyy-MM-dd. Both should work.
     */
    public function test_the_date_may_be_typed_or_come_from_the_calendar(): void
    {
        $this->match($this->main, 'W1');
        $page = $this->page();

        $page->call('selectDate', '12-09-2026')->assertSet('date', '2026-09-12');
        $page->call('selectDate', '2026-10-03')->assertSet('date', '2026-10-03');
    }

    public function test_a_nonsense_date_changes_nothing(): void
    {
        $this->match($this->main, 'W1');
        $page = $this->page();

        $page->call('selectDate', '12-09-2026')
            ->call('selectDate', '31-02-2026')
            ->assertSet('date', '2026-09-12');
    }

    public function test_the_arrows_go_to_the_previous_and_next_match_day(): void
    {
        $this->match($this->main, 'W1');
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'date' => '2026-09-26', 'time' => '09:00', 'match_type' => MatchType::Home,
        ]);

        $this->page()
            ->call('selectDate', '2026-09-19')
            ->call('navigate', 1)->assertSet('date', '2026-09-26')
            ->call('navigate', -1)->assertSet('date', '2026-09-12');
    }

    private function page(): Testable
    {
        return Livewire::test(HomeMatches::class)->set('versionId', $this->version->id);
    }

    private function match(Venue $venue, string $code): void
    {
        Court::query()->firstOrCreate(['venue_id' => $venue->id, 'name' => '1']);

        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $venue->id,
            'date' => '2026-09-12', 'time' => '09:00', 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'match_code' => $code,
        ]);
    }
}
