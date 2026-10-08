<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditAccount;
use App\Models\User;
use App\Planner\Filament\Widgets\AccountSecurityNotice;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use InteractsWithClubs;
    use RefreshDatabase;

    public function test_the_account_page_opens_from_the_club_workspace(): void
    {
        $user = $this->actingAsClubUser();

        $this->get(EditAccount::getUrl())
            ->assertOk()
            ->assertSee('Mijn account')
            ->assertSee('Passkeys')
            ->assertSee('Tweestapsverificatie (2FA)')
            ->assertSee($user->email);
    }

    public function test_the_name_can_be_changed_without_the_password(): void
    {
        $user = $this->actingAsClubUser();

        Livewire::test(EditAccount::class)
            ->fillForm(['name' => 'Nieuwe Naam'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nieuwe Naam', $user->refresh()->name);
    }

    public function test_a_new_email_address_needs_the_current_password(): void
    {
        $user = $this->actingAsClubUser();
        $user->forceFill(['password' => Hash::make('oud-wachtwoord')])->save();
        $original = $user->email;

        Livewire::test(EditAccount::class)
            ->fillForm(['email' => 'nieuw@example.test'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'required']);
        $this->assertSame($original, $user->refresh()->email);

        Livewire::test(EditAccount::class)
            ->fillForm(['email' => 'nieuw@example.test', 'currentPassword' => 'oud-wachtwoord'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('nieuw@example.test', $user->refresh()->email);
    }

    public function test_a_new_password_needs_the_current_one(): void
    {
        $user = $this->actingAsClubUser();
        $user->forceFill(['password' => Hash::make('oud-wachtwoord')])->save();

        Livewire::test(EditAccount::class)
            ->fillForm(['password' => 'Nieuw-wachtwoord-123', 'passwordConfirmation' => 'Nieuw-wachtwoord-123', 'currentPassword' => 'fout'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        $this->assertTrue(Hash::check('oud-wachtwoord', $user->refresh()->password));

        Livewire::test(EditAccount::class)
            ->fillForm(['password' => 'Nieuw-wachtwoord-123', 'passwordConfirmation' => 'Nieuw-wachtwoord-123', 'currentPassword' => 'oud-wachtwoord'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('Nieuw-wachtwoord-123', $user->refresh()->password));
    }

    public function test_the_dashboard_reminds_until_two_factor_is_on(): void
    {
        $user = $this->actingAsClubUser();

        $this->assertTrue(AccountSecurityNotice::canView());

        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->assertFalse(AccountSecurityNotice::canView());
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $user->getRawOriginal('app_authentication_secret'));
    }

    public function test_the_platform_panel_has_the_account_page_too(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create());

        $this->get('/admin/account')->assertOk()->assertSee('Mijn account');
    }

    public function test_passkeys_are_managed_after_confirming_the_password(): void
    {
        $user = $this->actingAsClubUser();
        $user->forceFill(['password' => Hash::make('Mijn-wachtwoord-1')])->save();

        Livewire::test(EditAccount::class)
            ->assertSee('Bevestig eerst je wachtwoord')
            ->callAction(TestAction::make('confirmPasswordForPasskeys')->schemaComponent('passkeyConfirm', schema: 'content'), data: ['password' => 'fout'])
            ->assertHasActionErrors(['password']);
        $this->assertNull(session('auth.password_confirmed_at'));

        Livewire::test(EditAccount::class)
            ->callAction(TestAction::make('confirmPasswordForPasskeys')->schemaComponent('passkeyConfirm', schema: 'content'), data: ['password' => 'Mijn-wachtwoord-1'])
            ->assertHasNoActionErrors()
            ->assertRedirect(EditAccount::getUrl());
        $this->assertNotNull(session('auth.password_confirmed_at'));

        Livewire::test(EditAccount::class)->assertDontSee('Bevestig eerst je wachtwoord');
    }

    public function test_platform_admins_must_set_up_strong_authentication(): void
    {
        config(['auth.require_platform_admin_mfa' => true]);
        $admin = User::factory()->platformAdmin()->create();

        $this->assertTrue($admin->mustSetUpStrongAuthentication());
        $this->actingAs($admin)->get('/admin')->assertRedirect();
        $this->get('/admin/account')->assertOk();

        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->assertFalse($admin->refresh()->mustSetUpStrongAuthentication());
    }

    public function test_a_club_can_require_strong_authentication_of_its_members(): void
    {
        $user = $this->actingAsClubUser();
        $this->assertFalse($user->mustSetUpStrongAuthentication());

        $user->clubs()->first()->update(['require_strong_auth' => true]);

        $this->assertTrue($user->refresh()->mustSetUpStrongAuthentication());
    }
}
