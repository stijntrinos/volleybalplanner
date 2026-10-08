<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditAccount;
use App\Models\ContactRequest;
use App\Models\User;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\ClubRole;
use App\Support\Accounts\AccountDeletion;
use App\Support\Accounts\PersonalData;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * AVG: a user downloads or deletes their own data on "Mijn account", and
 * personal data that served its purpose is pruned.
 */
class PersonalDataTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private function privacyAction(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('privacyActions', schema: 'content');
    }

    public function test_a_user_downloads_their_data_as_json(): void
    {
        $user = $this->actingAsClubUser(ClubRole::PlanningCommittee);
        $club = $user->clubs()->firstOrFail();

        Livewire::test(EditAccount::class)
            ->callAction($this->privacyAction('downloadData'))
            ->assertFileDownloaded('volleybalplanner-gegevens-'.now()->format('Y-m-d').'.json');

        $export = app(PersonalData::class)->export($user);
        $this->assertSame($user->email, $export['account']['email']);
        $this->assertSame([$club->name], array_column($export['clubs'], 'club'));
        $this->assertSame(ClubRole::PlanningCommittee->getLabel(), $export['clubs'][0]['role']);
    }

    public function test_a_member_deletes_their_account_and_the_planning_stays(): void
    {
        $user = $this->actingAsClubUser(ClubRole::PlanningCommittee);
        $user->forceFill(['password' => Hash::make('Mijn-wachtwoord-1')])->save();
        $version = ScheduleVersion::factory()->create(['created_by' => $user->id]);
        activity('planner-move')->causedBy($user)->log('verplaatst');

        Livewire::test(EditAccount::class)
            ->callAction($this->privacyAction('deleteAccount'), data: ['password' => 'fout'])
            ->assertHasActionErrors(['password']);
        $this->assertModelExists($user);

        Livewire::test(EditAccount::class)
            ->callAction($this->privacyAction('deleteAccount'), data: ['password' => 'Mijn-wachtwoord-1'])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('site.home', ['account' => 'verwijderd']));

        $this->assertModelMissing($user);
        $this->assertGuest();
        $this->assertNull($version->refresh()->created_by);
        $this->assertNull(Activity::query()->where('description', 'verplaatst')->value('causer_id'));
        $this->get(route('site.home', ['account' => 'verwijderd']))->assertSee('Je account is verwijderd');
    }

    public function test_the_only_club_admin_has_to_hand_over_first(): void
    {
        $user = $this->actingAsClubUser(ClubRole::ClubAdmin);
        $club = $user->clubs()->firstOrFail();

        Livewire::test(EditAccount::class)
            ->mountAction($this->privacyAction('deleteAccount'))
            ->assertMountedActionModalSee('Je bent de enige clubbeheerder van '.$club->name);
        $this->assertNotSame([], app(AccountDeletion::class)->blockers($user));

        // With a second club admin, the first may go.
        $other = User::factory()->create();
        $other->clubs()->attach($club);
        $other->assignClubRole($club, ClubRole::ClubAdmin);
        $this->assertSame([], app(AccountDeletion::class)->blockers($user));
    }

    public function test_platform_admins_cannot_delete_themselves(): void
    {
        $user = User::factory()->create(['is_platform_admin' => true]);

        $this->assertNotSame([], app(AccountDeletion::class)->blockers($user));
    }

    public function test_old_change_requests_are_pruned_for_every_club(): void
    {
        $old = [];
        foreach ([$this->createClub(), $this->createClub()] as $club) {
            $this->useClub($club);
            $old[] = ChangeRequest::factory()->create(['created_at' => now()->subMonths(13)]);
            ChangeRequest::factory()->create(['created_at' => now()->subMonths(2)]);
        }
        app(CurrentClub::class)->set(null);

        $this->artisan('model:prune', ['--model' => [ChangeRequest::class]])->assertSuccessful();

        $this->assertSame(2, ChangeRequest::withoutGlobalScope('club')->count());
        foreach ($old as $request) {
            $this->assertNull(ChangeRequest::withoutGlobalScope('club')->find($request->id));
        }
    }

    public function test_contact_requests_go_a_year_after_handling_or_after_two_years(): void
    {
        $make = function (array $attributes): int {
            $request = ContactRequest::create(['topic' => 'vraag', 'name' => 'Jan', 'club' => 'VC Test', 'email' => 'jan@example.test']);
            $request->forceFill($attributes)->save();

            return $request->id;
        };

        $make(['handled_at' => now()->subMonths(13)]);
        $make(['created_at' => now()->subYears(3)]);
        $kept = [$make(['handled_at' => now()->subMonth()]), $make(['created_at' => now()->subYear()])];

        $this->artisan('model:prune', ['--model' => [ContactRequest::class]])->assertSuccessful();

        $this->assertEqualsCanonicalizing($kept, ContactRequest::query()->pluck('id')->all());
    }
}
