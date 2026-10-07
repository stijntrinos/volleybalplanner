<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Filament\Pages\ImportPlanning;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Choosing "delete" for a match that is no longer in the source file must take
 * the page all the way to the import: check, choose, import.
 */
class ImportPlanningRemoveTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-10 08:00'));
        Storage::fake('local');

        $this->actingAsClubUser();
        PlannerSetting::current()->update(['team_name_patterns' => ['VV Demo']]);

        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $venue = Venue::factory()->create(['name' => 'De Laar']);
        Court::factory()->create(['venue_id' => $venue->id, 'name' => '1']);
        FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'ABC1',
            'date' => '2026-09-26', 'time' => '10:00', 'venue_id' => $venue->id,
            'original_date' => '2026-09-26', 'original_time' => '10:00', 'original_venue' => 'De Laar',
        ]);
    }

    public function test_a_removed_match_disappears_when_chosen(): void
    {
        $removed = FixtureMatch::factory()->create([
            'version_id' => $this->version->id, 'match_code' => 'GONE1', 'date' => '2026-10-10', 'time' => '10:00',
        ]);

        Livewire::test(ImportPlanning::class)
            ->fillForm(['file' => $this->file(), 'mode' => ImportPlanning::MODE_UPDATE, 'version_id' => $this->version->id])
            ->call('analyze')
            ->assertSet('importChoices.'.$removed->id, 'keep')
            ->set('importChoices.'.$removed->id, 'delete')
            ->call('import')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('planner_matches', ['id' => $removed->id]);
    }

    public function test_delete_all_removes_every_removed_match(): void
    {
        $removed = FixtureMatch::factory()->count(2)->sequence(['match_code' => 'GONE1'], ['match_code' => 'GONE2'])->create([
            'version_id' => $this->version->id, 'date' => '2026-10-10', 'time' => '10:00',
        ]);

        Livewire::test(ImportPlanning::class)
            ->fillForm(['file' => $this->file(), 'mode' => ImportPlanning::MODE_UPDATE, 'version_id' => $this->version->id])
            ->call('analyze')
            ->call('chooseForAll', 'removed', 'delete')
            ->call('import')
            ->assertHasNoErrors();

        foreach ($removed as $match) {
            $this->assertDatabaseMissing('planner_matches', ['id' => $match->id]);
        }
    }

    private function file(): UploadedFile
    {
        $lines = [
            'Datum;Tijd;Team thuis;Team uit;Zaal;Veld;Regio;Poule;Code;Zaalcode;Plaats',
            '26-09-2026;10:00;VV Demo HS1;Dynamo HS1;De Laar;1;Oost;H1;ABC1;;Arnhem',
        ];

        return UploadedFile::fake()->createWithContent('planning.csv', implode("\n", $lines)."\n");
    }
}
