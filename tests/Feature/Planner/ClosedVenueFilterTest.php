<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Services\ClosedVenueFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ClosedVenueFilterTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useClub($this->createClub());
        $this->venue = Venue::factory()->create();
    }

    public function test_a_closed_venue_has_no_trainings(): void
    {
        $this->rule(AvailabilityScope::Period, AvailabilityStatus::Closed, ['period_start' => '2026-10-17', 'period_end' => '2026-10-25']);
        $trainings = collect([$this->training('19:00', '20:30')]);

        $this->assertCount(0, $this->filter($trainings, '2026-10-20'));
        $this->assertCount(1, $this->filter($trainings, '2026-10-27'));
    }

    public function test_a_date_rule_available_lifts_a_closed_period(): void
    {
        $this->rule(AvailabilityScope::Period, AvailabilityStatus::Closed, ['period_start' => '2026-10-17', 'period_end' => '2026-10-25']);
        $this->rule(AvailabilityScope::Date, AvailabilityStatus::Available, ['date' => '2026-10-20']);

        $this->assertCount(1, $this->filter(collect([$this->training('19:00', '20:30')]), '2026-10-20'));
    }

    public function test_a_closure_with_times_only_removes_trainings_in_that_window(): void
    {
        $this->rule(AvailabilityScope::Date, AvailabilityStatus::Closed, ['date' => '2026-10-20', 'start_time' => '18:00', 'end_time' => '20:00']);
        $early = $this->training('17:00', '18:00');
        $during = $this->training('19:00', '20:30');
        // Start and end are required in the table; an unsaved training stands in for old data without times.
        $untimed = Training::factory()->make(['venue_id' => $this->venue->id, 'start_time' => null, 'end_time' => null])->forceFill(['id' => 999]);

        $left = $this->filter(collect([$early, $during, $untimed]), '2026-10-20');

        $this->assertSame([$early->id, $untimed->id], $left->pluck('id')->all());
    }

    private function filter($trainings, string $date)
    {
        return app(ClosedVenueFilter::class)->trainingsInOpenVenues($trainings, Availability::query()->get(), $date);
    }

    /** @param array<string, mixed> $attributes */
    private function rule(AvailabilityScope $scope, AvailabilityStatus $status, array $attributes): void
    {
        Availability::create(['venue_id' => $this->venue->id, 'scope' => $scope, 'status' => $status, ...$attributes]);
    }

    private function training(string $start, string $end): Training
    {
        return Training::factory()->create([
            'venue_id' => $this->venue->id, 'weekday' => 'dinsdag', 'start_time' => $start, 'end_time' => $end,
        ]);
    }
}
