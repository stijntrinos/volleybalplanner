<?php

namespace Tests\Feature;

use App\Support\Privacy\CookieConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_without_a_valid_choice_see_the_banner(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('Jij kiest welke cookies we gebruiken');
        $this->withCookie(CookieConsent::COOKIE, 'invalid')->get('/')->assertOk()->assertSee('Jij kiest welke cookies we gebruiken');
    }

    public function test_accepting_or_refusing_statistics_saves_an_encrypted_cookie(): void
    {
        foreach ([true => 'v1:analytics', false => 'v1:necessary'] as $choice => $value) {
            $this->from('/cookies')->post('/cookies', ['analytics' => $choice])
                ->assertRedirect('/cookies')
                ->assertCookie(CookieConsent::COOKIE, $value)
                ->assertSessionHas('cookie-preferences-saved', true);
        }
    }

    public function test_saved_choices_hide_the_banner_and_can_be_changed(): void
    {
        $this->withoutVite();

        $this->withCookie(CookieConsent::COOKIE, 'v1:analytics')->get('/')
            ->assertOk()->assertDontSee('Jij kiest welke cookies we gebruiken')->assertSee('Cookies en voorkeuren');
        $this->from('/cookies')->post('/cookies', ['analytics' => false])
            ->assertRedirect('/cookies')->assertCookie(CookieConsent::COOKIE, 'v1:necessary');
        $this->withCookie(CookieConsent::COOKIE, 'v1:necessary')->get('/')
            ->assertOk()->assertDontSee('Jij kiest welke cookies we gebruiken');
    }

    public function test_preferences_are_available_without_a_banner_and_invalid_input_is_rejected(): void
    {
        $this->withoutVite();

        $this->get('/cookies')->assertOk()->assertSee('Voorkeuren opslaan')->assertDontSee('Jij kiest welke cookies we gebruiken');
        $this->postJson('/cookies', [])->assertUnprocessable()->assertJsonValidationErrors('analytics')->assertCookieMissing(CookieConsent::COOKIE);
        $this->postJson('/cookies', ['analytics' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('analytics')->assertCookieMissing(CookieConsent::COOKIE);
    }
}
