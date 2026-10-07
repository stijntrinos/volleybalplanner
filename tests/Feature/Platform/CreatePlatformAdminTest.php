<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'stijn@example.nl']);

        $this->artisan('platform:create-admin', ['email' => 'Stijn@Example.nl'])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_platform_admin);
    }

    public function test_creates_a_new_admin(): void
    {
        $this->artisan('platform:create-admin', ['email' => 'nieuw@example.nl', '--name' => 'Nieuw'])
            ->expectsQuestion('Wachtwoord', 'geheim-wachtwoord')
            ->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'nieuw@example.nl')->is_platform_admin);
    }
}
