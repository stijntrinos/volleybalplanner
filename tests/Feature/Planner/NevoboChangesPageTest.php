<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\NevoboChanges;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\NevoboChange;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class NevoboChangesPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_progress_is_calculated_over_all_changes(): void
    {
        $page = new NevoboChanges;
        $page->changes = [
            ['fields' => [['processed' => false], ['processed' => true]]],
            ['fields' => [['processed' => false]]],
        ];

        $this->assertSame([
            'matches' => 2,
            'open' => 2,
            'processed' => 1,
            'total' => 3,
        ], $page->getStatisticsProperty());
    }

    public function test_change_values_get_clear_labels_and_formatting(): void
    {
        $page = new NevoboChanges;

        $this->assertSame('Wedstrijddatum', $page->fieldLabel('date'));
        $this->assertSame('Aanvangstijd', $page->fieldLabel('time'));
        $this->assertSame('Za. 08-08-2026', $page->formatValue('date', '2026-08-08'));
        $this->assertSame('Niet ingesteld', $page->formatValue('court', null));
    }

    public function test_the_version_picker_only_shows_drafts(): void
    {
        $this->useClub($this->createClub());
        $draft = ScheduleVersion::factory()->create(['name' => 'v12', 'status' => ScheduleVersionStatus::Draft]);
        ScheduleVersion::factory()->create(['name' => 'v11', 'status' => ScheduleVersionStatus::Published]);
        ScheduleVersion::factory()->create(['name' => 'v10', 'status' => ScheduleVersionStatus::Archived]);

        $versions = (new NevoboChanges)->getVersionsProperty();

        $this->assertSame([$draft->id], $versions->keys()->all(), 'Changes are applied to a draft.');
        $this->assertSame('v12 · concept', $versions->first());
    }

    public function test_all_changes_of_a_match_are_processed_in_one_click(): void
    {
        $this->actingAsClubUser();
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $match = FixtureMatch::factory()->create([
            'version_id' => $version->id, 'match_type' => MatchType::Home,
            'date' => '2026-09-19', 'time' => '20:00',
            'original_date' => '2026-09-12', 'original_time' => '19:30',
        ]);

        Livewire::test(NevoboChanges::class)
            ->assertSee($match->match_code)
            ->call('markAll', $match->id, true)
            ->assertHasNoErrors()
            ->assertSet('changes.0.fields.0.processed', true);

        $this->assertSame(2, NevoboChange::query()->where('match_id', $match->id)->where('is_processed', true)->count());
    }
}
