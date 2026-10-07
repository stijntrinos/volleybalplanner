<?php

namespace Tests\Feature\Planner\HomeMatches;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * A failed action should open a modal with the reason, not a generic
 * notification that is gone after five seconds. (Aetos FoutmodalTest)
 */
class ErrorModalTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    private Venue $venue;

    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
        $this->venue = Venue::factory()->create(['is_active' => true, 'for_matches' => true]);
        $this->court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);
        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
    }

    public function test_a_refused_move_opens_the_error_modal_with_the_reason(): void
    {
        $this->version->update(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $match = $this->match();

        $page = $this->page()->call('move', $match->id, $this->venue->id, $this->court->id, '19:30');

        $page->assertActionMounted('errorModal');
        $this->assertSame('10:45', substr((string) $match->refresh()->time, 0, 5));
    }

    public function test_a_successful_move_opens_no_error_modal(): void
    {
        $match = $this->match();

        $page = $this->page()->call('move', $match->id, $this->venue->id, $this->court->id, '19:30');

        $page->assertActionNotMounted('errorModal');
        $this->assertSame('19:30', substr((string) $match->refresh()->time, 0, 5));
    }

    public function test_read_only_stays_a_notification_and_no_modal(): void
    {
        $this->version->update(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        $match = $this->match();

        $page = $this->page()
            ->call('openMatch', $match->id)
            ->set('modalTime', '12:00')
            ->call('saveMatch');

        $page->assertActionNotMounted('errorModal');
        $this->assertSame('10:45', substr((string) $match->refresh()->time, 0, 5), 'a read-only schedule stays unchanged');
    }

    private function page(): mixed
    {
        return Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->set('date', '2026-10-10')
            ->call('load');
    }

    private function match(): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => $this->version->id,
            'venue_id' => $this->venue->id, 'court_id' => $this->court->id,
            'date' => '2026-10-10', 'time' => '10:45', 'duration_minutes' => 105,
            'match_type' => MatchType::Home, 'match_code' => 'W-1',
            'home_team_name' => 'VV Demo HS 1', 'away_team_name' => 'Tegenstander',
        ]);
    }
}
