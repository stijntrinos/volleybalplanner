<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Activities\Pages\ListActivities;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admins_can_read_the_activity_log(): void
    {
        $admin = User::factory()->platformAdmin()->create(['name' => 'Beheer']);
        $this->actingAs($admin);
        Filament::setCurrentPanel('platform');
        activity('impersonation')->causedBy($admin)->log('Impersonation started');

        Livewire::test(ListActivities::class)
            ->assertCanSeeTableRecords(Activity::all())
            ->assertSee('Inloggen als')
            ->assertSee('Beheer');

        $this->get('/admin/activiteit')->assertOk();
    }

    public function test_others_cannot_open_it(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/activiteit')->assertForbidden();
    }
}
