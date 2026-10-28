<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\EditClub;
use App\Filament\Platform\Resources\Clubs\RelationManagers\UsersRelationManager;
use App\Models\Club;
use App\Models\User;
use App\Planner\Support\ClubRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ClubUsersRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
        $this->club = Club::factory()->create();
    }

    private function relationManager(): Testable
    {
        return Livewire::test(UsersRelationManager::class, ['ownerRecord' => $this->club, 'pageClass' => EditClub::class]);
    }

    public function test_a_platform_admin_creates_a_user_with_a_password(): void
    {
        $this->relationManager()
            ->callAction(TestAction::make('createUser')->table(), [
                'name' => 'Jan Smit',
                'email' => 'Jan@Example.test',
                'role' => ClubRole::PlanningCommittee->value,
                'password' => 'Zomer-Volleybal-2026',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Gebruiker aangemaakt');

        $user = User::query()->where('email', 'jan@example.test')->sole();
        $this->assertTrue(Hash::check('Zomer-Volleybal-2026', $user->password));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($this->club->users()->whereKey($user->id)->exists());
        $this->assertSame(ClubRole::PlanningCommittee, $user->clubRole($this->club));
        Notification::assertNothingSent();
    }

    public function test_a_new_user_needs_a_password(): void
    {
        $this->relationManager()
            ->callAction(TestAction::make('createUser')->table(), ['name' => 'Jan', 'email' => 'jan@example.test', 'role' => ClubRole::ClubAdmin->value])
            ->assertHasFormErrors(['password' => 'required']);

        $this->assertFalse(User::query()->where('email', 'jan@example.test')->exists());
    }

    public function test_an_existing_account_joins_without_its_password_changing(): void
    {
        $existing = User::factory()->create(['email' => 'jan@example.test', 'password' => 'Eigen-Wachtwoord-1']);

        $this->relationManager()
            ->callAction(TestAction::make('createUser')->table(), [
                'name' => 'Jan',
                'email' => 'jan@example.test',
                'role' => ClubRole::ClubAdmin->value,
                'password' => 'Ander-Wachtwoord-2',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Bestaand account toegevoegd aan de club');

        $existing->refresh();
        $this->assertTrue(Hash::check('Eigen-Wachtwoord-1', $existing->password));
        $this->assertSame(ClubRole::ClubAdmin, $existing->clubRole($this->club));
        $this->assertSame(1, User::query()->where('email', 'jan@example.test')->count());
    }
}
