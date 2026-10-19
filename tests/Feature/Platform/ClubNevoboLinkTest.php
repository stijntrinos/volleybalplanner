<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Models\Club;
use App\Models\User;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Support\Tenancy\CurrentClub;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\FakesNevobo;
use Tests\TestCase;

class ClubNevoboLinkTest extends TestCase
{
    use FakesNevobo, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeNevobo();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
    }

    public function test_a_platform_admin_links_a_new_customer_to_nevobo(): void
    {
        $club = Club::factory()->create();
        $untouched = Club::factory()->create();

        Livewire::test(ListClubs::class)
            ->mountTableAction('linkNevobo', $club)
            ->setTableActionData(['club_code' => 'CNH8Q1U'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('Gekoppeld aan de Nevobo'));

        $teams = app(CurrentClub::class)->run($club, fn (): array => [
            Team::query()->orderBy('id')->pluck('code')->all(),
            TeamSource::query()->forImport()->count(),
            PlannerSetting::current()->nevobo_club_code,
        ]);
        $this->assertSame([['HS 1', 'DS 1', 'DS 11', 'MA 1', 'JC 1', 'XZ 1'], 6, 'CNH8Q1U'], $teams);
        $this->assertSame(0, Team::query()->withoutGlobalScopes()->where('club_id', $untouched->id)->count());
    }
}
