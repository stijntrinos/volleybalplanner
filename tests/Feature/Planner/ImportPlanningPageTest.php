<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\ImportPlanning;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ImportPlanningPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_a_new_file_choice_invalidates_an_old_check_result(): void
    {
        $page = new ImportPlanning;
        $page->report = ['usable' => 10];
        $page->verification = ['equal' => true];

        $page->updatedData();

        $this->assertNull($page->report);
        $this->assertNull($page->verification);
    }

    public function test_a_new_training_file_invalidates_an_old_check(): void
    {
        $page = new ImportPlanning;
        $page->trainingReport = ['valid' => 8];

        $page->updatedTrainingData();

        $this->assertNull($page->trainingReport);
    }

    public function test_the_page_renders_with_every_section(): void
    {
        $this->actingAsClubUser();

        Livewire::test(ImportPlanning::class)
            ->assertOk()
            ->assertSee('Trainingen importeren')
            ->assertSee('Diensten importeren')
            ->set('section', 'trainings')->assertOk()
            ->set('section', 'availability')->assertOk()
            ->set('section', 'duties')->assertOk()
            ->set('section', 'cleanup')->assertOk()->assertSee('Planningversies');
    }

    public function test_module_sections_are_hidden_without_the_module(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub([]));

        Livewire::test(ImportPlanning::class)
            ->assertDontSee('Trainingen importeren')
            ->assertDontSee('Diensten importeren')
            ->set('section', 'duties')
            ->assertSet('section', 'matches')
            ->call('analyzeTrainings')
            ->assertForbidden();
    }

    public function test_a_training_file_can_be_checked_and_imported(): void
    {
        Storage::fake('local');
        $this->actingAsClubUser();
        Venue::factory()->create(['name' => 'De Laar']);
        Team::factory()->create(['name' => 'HS 1', 'code' => 'HS 1']);

        $file = UploadedFile::fake()->createWithContent('trainingen.csv', "Sporthal;Dag;Begintijd;Eindtijd;Type veld;Veld;Team\nDe Laar;maandag;19:00;20:30;Heel;1;HS 1\n");

        Livewire::test(ImportPlanning::class)
            ->set('section', 'trainings')
            ->fillForm(['file' => $file], 'trainingForm')
            ->call('analyzeTrainings')
            ->assertHasNoErrors()
            ->assertSet('trainingReport.valid', 1)
            ->call('importTrainings')
            ->assertSet('trainingReport', null);

        $this->assertSame(1, Training::query()->count());
    }

    public function test_a_file_outside_the_club_import_directory_is_refused(): void
    {
        Storage::fake('local');
        $this->actingAsClubUser();
        Storage::disk('local')->put('planner-imports/999999/matches/other.csv', 'x');

        Livewire::test(ImportPlanning::class)
            ->set('data.file', ['planner-imports/999999/matches/other.csv'])
            ->set('data.mode', ImportPlanning::MODE_NEW)
            ->set('data.name', 'Test')
            ->call('analyze')
            ->assertForbidden();
    }

    public function test_the_page_is_core_and_available_without_modules(): void
    {
        $club = $this->createClub([]);
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->assertNotContains(PlannerFeature::Duties->value, $club->features ?? []);
        $this->get('/app/'.$club->slug.'/import-planning')->assertOk();
    }
}
