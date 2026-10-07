<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Refused edits and garbage in the URL should give a message, not an error
 * page. (Aetos ThuiswedstrijdenFoutafhandelingTest)
 */
class HomeMatchesErrorHandlingTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->club);
    }

    public function test_dragging_on_a_published_schedule_gives_a_message_not_an_error(): void
    {
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $venue = Venue::factory()->create();
        $court = Court::factory()->create(['venue_id' => $venue->id]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-19', 'time' => null, 'venue_id' => null, 'court_id' => null,
        ]);

        Livewire::test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->set('date', '2026-09-19')
            ->call('move', $match->id, $venue->id, $court->id, '19:30')
            ->assertOk();

        $this->assertNull($match->refresh()->venue_id);
    }

    public function test_the_planner_opens_on_today_when_there_are_matches_today(): void
    {
        $version = ScheduleVersion::factory()->create();
        $this->match($version, now()->subMonth()->toDateString());
        $this->match($version, now()->toDateString());
        $this->match($version, now()->addWeek()->toDateString());

        Livewire::test(HomeMatches::class)->assertSet('date', now()->toDateString());
    }

    public function test_without_matches_today_the_planner_opens_on_the_next_match_day(): void
    {
        $version = ScheduleVersion::factory()->create();
        $this->match($version, now()->subMonth()->toDateString());
        $next = now()->addWeek()->toDateString();
        $this->match($version, $next);

        Livewire::test(HomeMatches::class)->assertSet('date', $next);
    }

    public function test_a_finished_season_opens_on_the_last_match_day(): void
    {
        $version = ScheduleVersion::factory()->create();
        $this->match($version, now()->subMonths(2)->toDateString());
        $last = now()->subWeek()->toDateString();
        $this->match($version, $last);

        Livewire::test(HomeMatches::class)->assertSet('date', $last);
    }

    public function test_a_nonsense_date_in_the_url_falls_back_on_a_usable_day(): void
    {
        ScheduleVersion::factory()->create();

        $this->get(HomeMatches::getUrl(tenant: $this->club).'?datum=geen-datum')->assertOk();
        $this->get(HomeMatches::getUrl(tenant: $this->club).'?datum=2026-13-45')->assertOk();
    }

    private function match(ScheduleVersion $version, string $date): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_type' => MatchType::Home, 'date' => $date, 'time' => '10:00',
        ]);
    }
}
