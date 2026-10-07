<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Two matches on the same court and time lay exactly on top of each other:
 * the top card hid the bottom one, so the double booking only showed when
 * someone dragged the top one away. (Aetos ThuiswedstrijdenOverlapTest)
 */
class HomeMatchesOverlapTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $version;

    private Court $court;

    private Court $secondCourt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = $this->createClub();
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->club);
        $this->version = ScheduleVersion::factory()->create();
        $venue = Venue::factory()->create();
        $this->court = Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        $this->secondCourt = Court::factory()->create(['venue_id' => $venue->id, 'name' => '2']);
    }

    public function test_two_matches_on_the_same_court_and_time_stand_side_by_side(): void
    {
        $this->match('10:00', 'MB4', $this->court);
        $this->match('10:00', 'MA2', $this->court);

        $html = $this->planner();

        $this->assertStringContainsString('is-overlapping', $html);
        $this->assertStringContainsString('Dubbel geboekt', $html);
        $this->assertStringContainsString('left: calc(5px + 0 * (100% - 10px) / 2)', $html);
        $this->assertStringContainsString('left: calc(5px + 1 * (100% - 10px) / 2)', $html);
        $this->assertStringContainsString('width: calc((100% - 10px) / 2 - 4px)', $html);
    }

    public function test_a_match_alone_on_the_court_keeps_the_full_width(): void
    {
        $this->match('10:00', 'MB4', $this->court);

        $html = $this->planner();

        $this->assertStringNotContainsString('is-overlapping', $html);
        $this->assertStringNotContainsString('Dubbel geboekt', $html);
        $this->assertStringNotContainsString('width: calc((100%', $html);
    }

    public function test_matches_on_different_courts_do_not_overlap(): void
    {
        $this->match('10:00', 'MB4', $this->court);
        $this->match('10:00', 'MA2', $this->secondCourt);

        $this->assertStringNotContainsString('is-overlapping', $this->planner());
    }

    public function test_matches_that_follow_each_other_do_not_overlap(): void
    {
        $this->match('10:00', 'MB4', $this->court);
        $this->match('11:45', 'MA2', $this->court);

        $this->assertStringNotContainsString('is-overlapping', $this->planner());
    }

    private function match(string $time, string $code, Court $court): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'date' => '2026-09-12', 'time' => $time, 'duration_minutes' => 105,
            'venue_id' => $court->venue_id, 'court_id' => $court->id,
            'match_code' => $code, 'match_type' => MatchType::Home,
        ]);
    }

    private function planner(): string
    {
        return $this->get(HomeMatches::getUrl(tenant: $this->club).'?datum=2026-09-12')
            ->assertOk()
            ->getContent();
    }
}
