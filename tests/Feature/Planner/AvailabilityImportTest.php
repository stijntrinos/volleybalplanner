<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Court;
use App\Planner\Models\Venue;
use App\Planner\Services\AvailabilityImportService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\WritesImportFiles;
use Tests\TestCase;

class AvailabilityImportTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase, WritesImportFiles;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);
    }

    public function test_the_three_scopes_come_in_with_their_times(): void
    {
        $file = $this->csv([
            'sporthal;scope;datum;weekdag;periode van;periode tot;begintijd;eindtijd;status;veld;notitie',
            'Middachtensingel;datum;12-09-2026;;;;08:00;22:30;beschikbaar;;Inhuur zaterdag',
            'Middachtensingel;weekdag;;zondag;;;;;gesloten;;Zondag dicht',
            'Middachtensingel;periode;;;20-12-2026;04-01-2027;;;gesloten;;Kerstvakantie',
        ]);

        $outcome = $this->service()->import($file);

        $this->assertSame(3, $outcome['created']);
        $this->assertSame([], $outcome['errors']);

        $rental = Availability::query()->where('scope', AvailabilityScope::Date)->sole();
        $this->assertSame('2026-09-12', $rental->date->toDateString());
        $this->assertSame('08:00', substr((string) $rental->start_time, 0, 5));
        $this->assertSame('22:30', substr((string) $rental->end_time, 0, 5));
        $this->assertSame(AvailabilityStatus::Available, $rental->status);

        $this->assertSame(0, Availability::query()->where('scope', AvailabilityScope::Weekday)->value('weekday'), 'Sunday is 0.');
        $this->assertSame('2026-12-20', Availability::query()->where('scope', AvailabilityScope::Period)->first()->period_start->toDateString());
    }

    public function test_english_scope_and_status_values_are_accepted_too(): void
    {
        $file = $this->csv(['sporthal;scope;datum;status', 'Middachtensingel;date;12-09-2026;closed']);

        $this->assertSame(1, $this->service()->import($file)['created']);
        $this->assertSame(AvailabilityStatus::Closed, Availability::query()->sole()->status);
    }

    public function test_a_court_level_rule_is_linked(): void
    {
        $file = $this->csv(['sporthal;scope;datum;status;veld', 'Middachtensingel;datum;12-09-2026;gesloten;1']);

        $this->service()->import($file);

        $this->assertNotNull(Availability::query()->sole()->court_id);
    }

    public function test_unusable_rows_are_reported_with_reason_and_not_written(): void
    {
        $file = $this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Onbekende Hal;datum;12-09-2026;;;gesloten',
            'Middachtensingel;kwartaal;12-09-2026;;;gesloten',
            'Middachtensingel;datum;12-09-2026;20:00;;gesloten',
            'Middachtensingel;datum;12-09-2026;22:00;20:00;gesloten',
            'Middachtensingel;datum;;;;gesloten',
        ]);

        $outcome = $this->service()->import($file);

        $this->assertSame(0, $outcome['created']);
        $this->assertCount(5, $outcome['errors']);
        $this->assertSame(0, Availability::query()->count());

        $reasons = implode(' | ', array_column($outcome['errors'], 'reason'));
        $this->assertStringContainsString('Onbekende sporthal', $reasons);
        $this->assertStringContainsString('Scope moet', $reasons);
        $this->assertStringContainsString('allebei', $reasons);
        $this->assertStringContainsString('Eindtijd moet na begintijd', $reasons);
        $this->assertStringContainsString('Datum is verplicht', $reasons);
    }

    public function test_importing_the_same_rule_twice_updates_it(): void
    {
        $first = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Middachtensingel;datum;12-09-2026;08:00;22:30;beschikbaar',
        ]));
        $this->assertSame(1, $first['created'], 'The first import should create the rule.');
        $this->assertSame([], $first['errors']);

        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Middachtensingel;datum;12-09-2026;09:00;21:00;beschikbaar',
        ]));

        $this->assertSame(0, $outcome['created']);
        $this->assertSame(1, $outcome['updated']);
        $this->assertSame(1, Availability::query()->count(), 'No duplicate rule should be added.');
        $this->assertSame('09:00', substr((string) Availability::query()->sole()->start_time, 0, 5));
    }

    public function test_analyzing_writes_nothing_yet(): void
    {
        $file = $this->csv([
            'sporthal;scope;datum;status',
            'Middachtensingel;datum;12-09-2026;gesloten',
            'Onbekende Hal;datum;12-09-2026;gesloten',
        ]);

        $outcome = $this->service()->analyze($file);

        $this->assertSame(1, $outcome['valid']);
        $this->assertCount(1, $outcome['errors']);
        $this->assertSame(0, Availability::query()->count());
    }

    /**
     * Excel stores a time as a fraction of a day: 19:00 is 0.791666 in the
     * file. That was read as "0" hours and "79" minutes.
     */
    public function test_a_time_as_excel_fraction_is_converted(): void
    {
        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status;notitie',
            'Middachtensingel;datum;24-09-2026;0.7916666666666666;0.9166666666666666;beschikbaar;Definitieve zaalhuur',
        ]));

        $this->assertSame([], $outcome['errors']);
        $rule = Availability::query()->sole();
        $this->assertSame('19:00', substr((string) $rule->start_time, 0, 5));
        $this->assertSame('22:00', substr((string) $rule->end_time, 0, 5));
    }

    public function test_a_time_with_a_dot_stays_a_time(): void
    {
        $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Middachtensingel;datum;24-09-2026;19.30;22.00;beschikbaar',
        ]));

        $this->assertSame('19:30', substr((string) Availability::query()->value('start_time'), 0, 5));
    }

    /** Excel stores a date as day number since 30-12-1899: 12-09-2026 is 46277. */
    public function test_a_date_as_excel_number_is_converted(): void
    {
        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Middachtensingel;datum;46277;08:00;22:30;beschikbaar',
        ]));

        $this->assertSame([], $outcome['errors']);
        $this->assertSame('2026-09-12', Availability::query()->sole()->date->toDateString());
    }

    public function test_a_period_as_excel_number_is_converted(): void
    {
        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;periode van;periode tot;status',
            'Middachtensingel;periode;46376;46391;gesloten',
        ]));

        $this->assertSame([], $outcome['errors']);
        $rule = Availability::query()->sole();
        $this->assertSame('2026-12-20', $rule->period_start->toDateString());
        $this->assertSame('2027-01-04', $rule->period_end->toDateString());
    }

    public function test_a_cell_with_only_a_year_stays_an_error(): void
    {
        $outcome = $this->service()->import($this->csv(['sporthal;scope;datum;status', 'Middachtensingel;datum;2026;gesloten']));

        $this->assertSame(0, $outcome['created']);
        $this->assertStringContainsString('Datum is verplicht', $outcome['errors'][0]['reason']);
    }

    public function test_an_unreadable_time_reports_the_row_instead_of_failing_the_import(): void
    {
        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Middachtensingel;datum;24-09-2026;kwart voor acht;22:00;beschikbaar',
        ]));

        $this->assertSame(0, $outcome['created']);
        $this->assertCount(1, $outcome['errors']);
        $this->assertStringContainsString('kwart voor acht', $outcome['errors'][0]['reason']);
    }

    public function test_venues_of_another_club_are_unknown_and_its_rules_untouched(): void
    {
        $other = $this->createClub();
        $otherRule = app(CurrentClub::class)->run($other, function (): Availability {
            $venue = Venue::factory()->create(['name' => 'Elders']);

            return Availability::factory()->create([
                'venue_id' => $venue->id, 'scope' => AvailabilityScope::Date, 'date' => '2026-09-12',
                'start_time' => '08:00', 'end_time' => '12:00', 'status' => AvailabilityStatus::Available,
            ]);
        });

        $outcome = $this->service()->import($this->csv([
            'sporthal;scope;datum;begintijd;eindtijd;status',
            'Elders;datum;12-09-2026;09:00;21:00;gesloten',
            'Middachtensingel;datum;12-09-2026;09:00;21:00;gesloten',
        ]));

        $this->assertSame(1, $outcome['created']);
        $this->assertStringContainsString('Onbekende sporthal', $outcome['errors'][0]['reason']);
        $this->assertSame('08:00', substr((string) Availability::withoutGlobalScopes()->find($otherRule->id)->start_time, 0, 5));
    }

    /** @param  list<string>  $lines */
    private function csv(array $lines): string
    {
        return $this->rawCsv(implode("\n", $lines));
    }

    private function service(): AvailabilityImportService
    {
        return app(AvailabilityImportService::class);
    }
}
