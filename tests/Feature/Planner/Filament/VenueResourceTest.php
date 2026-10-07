<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Filament\Resources\Venues\Pages\CreateVenue;
use App\Planner\Filament\Resources\Venues\Pages\EditVenue;
use App\Planner\Filament\Resources\Venues\RelationManagers\AliasesRelationManager;
use App\Planner\Filament\Resources\Venues\RelationManagers\CourtsRelationManager;
use App\Planner\Models\Court;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Ported from Aetos VeldenBeheerTest + SporthalDuplicaatTest. */
class VenueResourceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsClubUser();

        $this->venue = Venue::factory()->create([
            'name' => 'Valkenhuizen - Hal 5',
            'city' => 'Arnhem',
            'venue_code' => 'VH05',
        ]);
    }

    public function test_the_planning_committee_can_delete_a_court(): void
    {
        $court = Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);

        $this->courts()
            ->callTableAction('delete', $court)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($court);
    }

    public function test_the_same_name_and_city_gives_a_clear_message(): void
    {
        Livewire::test(CreateVenue::class)
            ->fillForm(['name' => 'Valkenhuizen - Hal 5', 'city' => 'Arnhem', 'source' => 'manual'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'Er bestaat al een sporthal met deze naam in deze plaats.']);

        $this->assertSame(1, Venue::withoutGlobalScopes()->count());
    }

    public function test_the_same_name_in_another_city_is_allowed(): void
    {
        Livewire::test(CreateVenue::class)
            ->fillForm(['name' => 'Valkenhuizen - Hal 5', 'city' => 'Velp', 'source' => 'manual'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, Venue::withoutGlobalScopes()->count());
    }

    public function test_an_existing_venue_code_gives_a_clear_message(): void
    {
        Livewire::test(CreateVenue::class)
            ->fillForm(['name' => 'Rijkerswoerd', 'city' => 'Arnhem', 'venue_code' => 'VH05', 'source' => 'manual'])
            ->call('create')
            ->assertHasFormErrors(['venue_code' => 'Deze zaalcode is al aan een andere sporthal gekoppeld.']);
    }

    public function test_an_empty_venue_code_stays_allowed(): void
    {
        Venue::factory()->create(['name' => 'Elderveld', 'city' => 'Arnhem', 'venue_code' => null]);

        Livewire::test(CreateVenue::class)
            ->fillForm(['name' => 'De Overkant', 'city' => 'Arnhem', 'venue_code' => null, 'source' => 'manual'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_editing_a_venue_does_not_clash_with_itself(): void
    {
        Livewire::test(EditVenue::class, ['record' => $this->venue->getKey()])
            ->fillForm(['address' => 'Beethovenlaan 1'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Beethovenlaan 1', $this->venue->refresh()->address);
    }

    public function test_a_name_of_another_club_does_not_block(): void
    {
        app(CurrentClub::class)->run($this->createClub(), fn () => Venue::factory()->create([
            'name' => 'Rijkerswoerd', 'city' => 'Arnhem', 'venue_code' => 'VH05',
        ]));

        Livewire::test(CreateVenue::class)
            ->fillForm(['name' => 'Rijkerswoerd', 'city' => 'Arnhem', 'source' => 'manual'])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_two_courts_with_the_same_name_in_a_venue_are_refused(): void
    {
        Court::factory()->create(['venue_id' => $this->venue->id, 'name' => '1']);

        $this->courts()
            ->callTableAction('create', data: ['name' => '1', 'source' => 'manual', 'sort_order' => 0])
            ->assertHasTableActionErrors(['name' => 'Deze sporthal heeft al een veld met deze naam.']);

        $this->assertSame(1, Court::withoutGlobalScopes()->count());
    }

    public function test_the_same_court_name_in_another_venue_is_allowed(): void
    {
        $second = Venue::factory()->create();
        Court::factory()->create(['venue_id' => $second->getKey(), 'name' => '1']);

        $this->courts()
            ->callTableAction('create', data: ['name' => '1', 'source' => 'manual', 'sort_order' => 0])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, Court::withoutGlobalScopes()->count());
        $this->assertSame($this->venue->club_id, Court::query()->latest('id')->value('club_id'));
    }

    public function test_an_existing_import_name_is_refused(): void
    {
        VenueAlias::create(['venue_id' => $this->venue->id, 'name' => 'Valkenhuizen']);

        Livewire::test(AliasesRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class])
            ->callTableAction('create', data: ['name' => 'Valkenhuizen'])
            ->assertHasTableActionErrors(['name' => 'Deze importnaam is al aan een sporthal gekoppeld.']);

        $this->assertSame(1, VenueAlias::withoutGlobalScopes()->count());
    }

    private function courts(): mixed
    {
        return Livewire::test(CourtsRelationManager::class, ['ownerRecord' => $this->venue, 'pageClass' => EditVenue::class]);
    }
}
