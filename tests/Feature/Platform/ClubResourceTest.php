<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
use App\Filament\Platform\Resources\Clubs\Pages\EditClub;
use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Models\Club;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClubResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
    }

    public function test_platform_admin_creates_a_club_with_features(): void
    {
        Livewire::test(CreateClub::class)
            ->fillForm(['name' => 'VV Test', 'slug' => 'vv-test', 'is_active' => true, 'features' => ['duties', 'volunteer_points']])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['duties', 'volunteer_points'], Club::firstWhere('slug', 'vv-test')->features);
    }

    public function test_feature_dependencies_are_enforced(): void
    {
        Livewire::test(CreateClub::class)
            ->fillForm(['name' => 'VV Test', 'slug' => 'vv-test', 'features' => ['volunteer_points']])
            ->call('create')
            ->assertHasFormErrors(['features']);
    }

    public function test_reserved_and_malformed_slugs_are_rejected(): void
    {
        foreach (['admin', 'app', 'VV Test', 'vv_test', '-vv'] as $slug) {
            Livewire::test(CreateClub::class)
                ->fillForm(['name' => 'X', 'slug' => $slug])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }

    public function test_clubs_link_straight_to_their_workspace(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-link']);

        Livewire::test(ListClubs::class)
            ->assertTableActionHasUrl('openClub', url('/app/vv-link'), $club);

        Livewire::test(EditClub::class, ['record' => $club->getRouteKey()])
            ->assertActionHasUrl('openClub', url('/app/vv-link'));
    }

    public function test_dashboard_shows_every_club_as_a_card_opening_in_a_new_tab(): void
    {
        Club::factory()->create(['name' => 'VV Actief', 'slug' => 'vv-actief']);
        Club::factory()->inactive()->create(['name' => 'VV Slapend', 'slug' => 'vv-slapend']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('VV Actief')
            ->assertSee('VV Slapend')
            ->assertSee('href="'.url('/app/vv-actief').'" target="_blank"', false);
    }
}
