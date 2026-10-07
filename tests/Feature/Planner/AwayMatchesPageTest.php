<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\AwayMatches;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class AwayMatchesPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private FixtureMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsClubUser();

        // A draft: editing writes into the schedule, which is only allowed in a draft.
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match = FixtureMatch::factory()->create([
            'version_id' => $version->id,
            'match_type' => MatchType::Away,
            'date' => '2026-09-12',
            'time' => '15:00',
        ]);
    }

    public function test_an_away_match_can_be_changed(): void
    {
        Livewire::test(AwayMatches::class)
            ->call('edit', $this->match->id)
            ->assertSet('editMatchId', $this->match->id)
            ->set('editing.status', 'in_progress')
            ->set('editing.date', '19-09-2026')
            ->set('editing.time', '19:30')
            ->set('editing.venue', 'Sporthal De Laar')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editMatchId', null);

        $after = $this->match->fresh();
        $this->assertSame(AwayChangeStatus::InProgress, $after->away_change_status);
        $this->assertSame('2026-09-19', $after->away_new_date?->toDateString());
        $this->assertSame('19:30', substr((string) $after->away_new_time, 0, 5));
        $this->assertSame('Sporthal De Laar', $after->away_new_venue);
    }

    public function test_a_published_schedule_is_left_alone(): void
    {
        $published = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $published->id, 'match_type' => MatchType::Away, 'match_code' => 'PUB-1',
            'date' => '2026-09-12', 'time' => '15:00',
        ]);

        Livewire::test(AwayMatches::class)
            ->set('versionId', $published->id)
            ->call('edit', $match->id)
            ->set('editing.status', 'in_progress')
            ->set('editing.date', '19-09-2026')
            ->call('save')
            ->assertSet('editMatchId', $match->id);

        $this->assertSame(AwayChangeStatus::None, $match->fresh()->away_change_status ?? AwayChangeStatus::None);
    }

    public function test_the_newest_draft_is_selected_by_default(): void
    {
        ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $newestDraft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);

        Livewire::test(AwayMatches::class)->assertSet('versionId', $newestDraft->id);
    }

    public function test_the_page_renders_the_away_matches(): void
    {
        Livewire::test(AwayMatches::class)
            ->assertOk()
            ->assertSee($this->match->match_code);
    }
}
