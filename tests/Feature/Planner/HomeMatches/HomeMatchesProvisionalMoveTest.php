<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Dragging a match to another day is a proposal. The timetable draws it as a
 * marker on the new day, and the match itself on the old day says where it
 * is going. (Aetos VoorlopigeVerplaatsingTest::test_het_rooster_tekent_het_voorstel_als_markering)
 */
class HomeMatchesProvisionalMoveTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_the_timetable_draws_the_proposal_as_a_marker(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-08 12:00'));
        $this->actingAsClubUser();
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create(['name' => 'Middachtensingel', 'for_matches' => true]);
        $court1 = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1', 'sort_order' => 1]);
        $court2 = Court::factory()->create(['venue_id' => $venue->id, 'name' => '2', 'sort_order' => 2]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_code' => 'W-1', 'date' => '2026-09-12', 'time' => '10:00',
            'duration_minutes' => 105, 'match_type' => MatchType::Home,
            'venue_id' => $venue->id, 'court_id' => $court1->id, 'second_court_id' => null,
            'home_team_name' => 'VV Demo JB 1', 'away_team_name' => 'SV Dynamo Apeldoorn JB 1',
        ]);
        app(PlannerService::class)->move($match, [
            'date' => '2026-09-19', 'time' => '12:00', 'duration_minutes' => 105,
            'venue_id' => $venue->id, 'court_id' => $court2->id, 'second_court_id' => null,
        ]);

        Livewire::withQueryParams(['datum' => '2026-09-19'])->test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->set('date', '2026-09-19')
            ->call('load')
            ->assertSeeHtml('is-provisional')
            ->assertSee('Voorstel · staat op 12-09-2026');

        Livewire::withQueryParams(['datum' => '2026-09-12'])->test(HomeMatches::class)
            ->set('versionId', $version->id)
            ->set('date', '2026-09-12')
            ->call('load')
            ->assertDontSeeHtml('is-provisional')
            ->assertSee('Naar 19-09-2026');
    }
}
