<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\PlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The bar shows every venue where something happens that day, but only the
 * own venue is open. Showing everything at once made the timetable so wide
 * that you lost the venue that mattered. The main venue comes first: in
 * alphabetical order "Middachtensingel" ended up below "De Laar", while most
 * home matches are played there.
 */
class PlannerVenueSelectionTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $main;

    private Venue $second;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        // Alphabetically "De Laar" comes first; the main venue still belongs before it.
        $this->second = Venue::factory()->create(['name' => 'De Laar', 'is_active' => true, 'for_matches' => true]);
        $this->main = Venue::factory()->create(['name' => 'Middachtensingel', 'is_active' => true, 'for_matches' => true]);
        $this->version = ScheduleVersion::factory()->create();
        PlannerSetting::current()->update(['main_venue_id' => $this->main->id]);
    }

    public function test_the_day_planner_puts_the_main_venue_column_first(): void
    {
        $this->match($this->second, 'W1');
        $this->match($this->main, 'W2');

        $this->assertSame(['Middachtensingel', 'De Laar'], array_column($this->day()['venues'], 'name'));
    }

    public function test_only_the_main_venue_is_open_by_default(): void
    {
        $this->match($this->main, 'W1');
        $this->match($this->second, 'W2');

        $day = $this->day();

        $this->assertSame(['Middachtensingel', 'De Laar'], array_column($day['venues'], 'name'), 'Both venues belong in the bar.');
        $this->assertSame([$this->main->id], $day['default_venues']);
    }

    public function test_a_venue_with_only_training_is_in_the_bar_but_not_open(): void
    {
        $this->match($this->main, 'W1');
        $team = Team::factory()->create(['code' => 'DS 1']);
        Training::factory()->create([
            'team_id' => $team->id, 'venue_id' => $this->second->id,
            'weekday' => 'zaterdag', 'start_time' => '09:00', 'end_time' => '10:30', 'court' => 1,
        ]);

        $day = $this->day();

        $this->assertContains('De Laar', array_column($day['venues'], 'name'));
        $this->assertSame([$this->main->id], $day['default_venues']);
    }

    public function test_without_a_match_in_the_main_venue_the_venues_played_in_open(): void
    {
        $this->match($this->second, 'W1');

        $this->assertSame([$this->second->id], $this->day()['default_venues']);
    }

    public function test_the_bar_shows_the_number_of_matches_per_venue(): void
    {
        $this->match($this->main, 'W1');
        $this->match($this->main, 'W2');
        $this->match($this->second, 'W3');

        $counts = collect($this->day()['venues'])->pluck('matches', 'name')->all();

        $this->assertSame(2, $counts['Middachtensingel']);
        $this->assertSame(1, $counts['De Laar']);
    }

    public function test_an_away_match_does_not_count_in_the_bar(): void
    {
        $this->match($this->main, 'W1');
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'venue_id' => $this->main->id,
            'date' => '2026-09-12', 'time' => '12:00', 'duration_minutes' => 105,
            'match_type' => MatchType::Away, 'match_code' => 'U1',
        ]);

        $this->assertSame(1, collect($this->day()['venues'])->firstWhere('name', 'Middachtensingel')['matches']);
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

    /** @return array<string, mixed> */
    private function day(): array
    {
        return app(PlannerService::class)->dayData($this->version, '2026-09-12');
    }
}
