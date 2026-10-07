<?php

namespace Tests\Feature\Planner;

use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Services\TrainingImportService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

class TrainingImportServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
    }

    public function test_import_with_alias_and_team_normalization_is_idempotent(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Dumpel']);
        VenueAlias::create(['venue_id' => $venue->id, 'name' => 'Dumpel']);
        $team = Team::factory()->create(['name' => 'DS 1', 'code' => 'DS1']);
        $file = $this->csv("Dumpel;Maandag;19:30;21:00;Heel;2;D1\n");

        $first = $this->service()->import($file);
        $second = $this->service()->import($file);

        $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0, 'errors' => []], $first);
        $this->assertSame(['created' => 0, 'updated' => 1, 'skipped' => 0, 'errors' => []], $second);
        $this->assertSame(1, Training::query()->count());
        $this->assertSame($team->id, Training::query()->first()->team_id);
    }

    public function test_a_team_code_with_a_space_is_recognised(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);
        $team = Team::factory()->create(['name' => 'AETOS DS 7', 'code' => 'DS 7']);

        $report = $this->service()->analyze($this->csv("Elderveld;maandag;19:30;21:00;Training;2;DS 7\n"));

        $this->assertSame([], $report['errors']);
        $this->assertSame(1, $report['valid']);
        $this->assertSame($team->id, $report['examples'][0]['team_id']);
    }

    /**
     * A row without a team is free hall time: the court is occupied, just not
     * assigned yet. It belongs in the planning.
     */
    public function test_rows_without_team_come_in_as_free_court(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);
        Team::factory()->create(['name' => 'AETOS MC 2', 'code' => 'MC 2']);

        $report = $this->service()->analyze($this->csv("Elderveld;maandag;17:30;19:30;Training;3;MC 2\nElderveld;maandag;19:30;21:00;Training;4;\n"));

        $this->assertSame(2, $report['valid']);
        $this->assertSame(0, $report['skipped']);
        $this->assertSame([], $report['errors']);
    }

    public function test_a_free_court_is_written_without_team(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);

        $this->service()->import($this->csv("Elderveld;maandag;19:30;21:00;Training;4;\n"));

        $training = Training::query()->sole();
        $this->assertNull($training->team_id);
        $this->assertSame('maandag', $training->weekday);
        $this->assertSame(4, $training->court);
    }

    public function test_two_free_courts_at_the_same_time_coexist(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);

        $this->service()->import($this->csv("Elderveld;maandag;19:30;21:00;Training;3;\nElderveld;maandag;19:30;21:00;Training;4;\n"));

        $this->assertSame([3, 4], Training::query()->orderBy('court')->pluck('court')->all());
    }

    public function test_a_really_unknown_team_stays_an_error(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);

        $report = $this->service()->analyze($this->csv("Elderveld;maandag;19:30;21:00;Training;2;JA +\n"));

        $this->assertSame(0, $report['valid']);
        $this->assertSame(0, $report['skipped']);
        $this->assertStringContainsString('Onbekend team', $report['errors'][0]['reason']);
    }

    public function test_importing_also_counts_the_free_courts(): void
    {
        Venue::factory()->create(['name' => 'Elderveld']);
        Team::factory()->create(['name' => 'AETOS MC 2', 'code' => 'MC 2']);

        $result = $this->service()->import($this->csv("Elderveld;maandag;17:30;19:30;Training;3;MC 2\nElderveld;maandag;19:30;21:00;Training;4;\n"));

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['errors']);
    }

    public function test_an_invalid_row_does_not_block_a_valid_row(): void
    {
        $venue = Venue::factory()->create(['name' => 'De Dumpel']);
        Team::factory()->create(['name' => 'HS 10', 'code' => 'HS10']);

        $report = $this->service()->analyze($this->csv("De Dumpel;Dinsdag;0.75;21:00;Heel;1;H10\nOnbekend;Fout;21:00;20:00;Heel;8;X1\n"));

        $this->assertSame(1, $report['valid']);
        $this->assertCount(1, $report['errors']);
        $this->assertSame(3, $report['errors'][0]['row']);
        $this->assertSame($venue->id, $report['examples'][0]['venue_id']);
        $this->assertSame('HS 10', $report['examples'][0]['team']);
        $this->assertSame('18:00:00', $report['examples'][0]['start_time']);
    }

    public function test_teams_and_venues_of_another_club_are_not_used(): void
    {
        // Only the other club knows this team and this venue.
        app(CurrentClub::class)->run($this->createClub(), function (): void {
            Team::factory()->create(['code' => 'HS1', 'name' => 'Heren 1']);
            Venue::factory()->create(['name' => 'De Laar']);
        });

        $report = $this->service()->analyze($this->csv("De Laar;maandag;18:00;19:30;heel;1;HS 1\n"));

        $this->assertEmpty($report['examples']);
        $this->assertNotEmpty($report['errors'], 'A team of another club should be unknown');
    }

    public function test_without_the_trainings_module_the_import_refuses(): void
    {
        $this->useClub($this->createClub([PlannerFeature::Duties->value]));

        $this->expectException(RuntimeException::class);
        $this->service()->analyze($this->csv("Elderveld;maandag;19:30;21:00;Training;4;\n"));
    }

    private function csv(string $rows): string
    {
        return $this->rawCsv("Sporthal;Dag;Begintijd;Eindtijd;Type veld;Veld;Team\n{$rows}");
    }

    private function service(): TrainingImportService
    {
        return app(TrainingImportService::class);
    }
}
