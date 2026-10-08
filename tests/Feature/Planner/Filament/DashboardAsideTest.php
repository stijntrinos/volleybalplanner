<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Filament\Widgets\DashboardAside;
use App\Planner\Models\ChangeRequest;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DashboardAsideTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_requests_stay_hidden_without_the_right_to_list_them(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee);
        ChangeRequest::factory()->create(['status' => ChangeRequestStatus::Open, 'explanation' => 'Geheime toelichting']);

        Livewire::test(DashboardAside::class)->assertSee('Geheime toelichting');

        Role::findByName(ClubRole::PlanningCommittee->value)->revokePermissionTo('ViewAny:ChangeRequest');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Livewire::test(DashboardAside::class)->assertDontSee('Geheime toelichting');
    }

    public function test_a_request_cannot_be_decided_when_the_module_is_off(): void
    {
        $this->actingAsClubUser(ClubRole::ClubAdmin, $this->createClub(['duties']));
        $request = ChangeRequest::factory()->create(['status' => ChangeRequestStatus::Open]);

        Livewire::test(DashboardAside::class)
            ->call('decide', $request->id, ChangeRequestStatus::Accepted->value)
            ->assertNotFound();

        $this->assertSame(ChangeRequestStatus::Open, $request->fresh()->status);
    }
}
