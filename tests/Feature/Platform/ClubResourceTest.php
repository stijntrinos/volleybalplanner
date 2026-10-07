<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\CreateClub;
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
}
