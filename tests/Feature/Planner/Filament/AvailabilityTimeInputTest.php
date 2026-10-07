<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Filament\Resources\Availabilities\Pages\CreateAvailability;
use App\Planner\Filament\Resources\Availabilities\Pages\EditAvailability;
use App\Planner\Models\Availability;
use App\Planner\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * Ported from Aetos BeschikbaarheidTijdInvoerTest: times used to be clicked
 * from a list, which cost too many steps for a season of rental rules. They
 * are now typed as "18:30".
 */
class AvailabilityTimeInputTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();
        $this->venue = Venue::factory()->create(['name' => 'De Laar', 'is_managed' => true]);
    }

    public function test_a_typed_time_is_saved(): void
    {
        Livewire::test(CreateAvailability::class)
            ->fillForm($this->rule(['start_time' => '18:30', 'end_time' => '23:00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $rule = Availability::query()->firstOrFail();

        $this->assertSame('18:30', substr((string) $rule->start_time, 0, 5));
        $this->assertSame('23:00', substr((string) $rule->end_time, 0, 5));
        $this->assertTrue($rule->hasTimeWindow());
        $this->assertSame($this->venue->club_id, $rule->club_id);
    }

    public function test_a_time_outside_the_half_hours_is_allowed(): void
    {
        Livewire::test(CreateAvailability::class)
            ->fillForm($this->rule(['start_time' => '18:45', 'end_time' => '22:15']))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('18:45', substr((string) Availability::query()->value('start_time'), 0, 5));
    }

    public function test_without_times_the_rule_applies_all_day(): void
    {
        Livewire::test(CreateAvailability::class)
            ->fillForm($this->rule())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Availability::query()->firstOrFail()->hasTimeWindow());
    }

    public function test_a_nonsense_time_is_refused(): void
    {
        Livewire::test(CreateAvailability::class)
            ->fillForm($this->rule(['start_time' => '99:99', 'end_time' => '23:00']))
            ->call('create')
            ->assertHasFormErrors(['start_time']);
    }

    public function test_an_end_time_before_the_start_time_is_refused(): void
    {
        Livewire::test(CreateAvailability::class)
            ->fillForm($this->rule(['start_time' => '22:00', 'end_time' => '18:30']))
            ->call('create')
            ->assertHasFormErrors(['end_time']);
    }

    public function test_a_saved_time_comes_back_without_seconds_in_the_form(): void
    {
        $rule = Availability::create([
            'venue_id' => $this->venue->id,
            'scope' => AvailabilityScope::Date, 'date' => '2026-09-25',
            'start_time' => '18:30:00', 'end_time' => '23:00:00',
            'status' => AvailabilityStatus::Available,
        ]);

        Livewire::test(EditAvailability::class, ['record' => $rule->getKey()])
            ->assertFormSet(['start_time' => '18:30', 'end_time' => '23:00']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function rule(array $extra = []): array
    {
        return array_merge([
            'venue_id' => $this->venue->id,
            'scope' => AvailabilityScope::Date->value,
            'date' => '2026-09-25',
            'status' => AvailabilityStatus::Available->value,
        ], $extra);
    }
}
