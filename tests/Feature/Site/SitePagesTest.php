<?php

namespace Tests\Feature\Site;

use App\Enums\ContactTopic;
use App\Filament\Platform\Resources\ContactRequests\Pages\ListContactRequests;
use App\Models\ContactRequest;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_every_page_of_the_site_renders(): void
    {
        foreach (['/privacy' => 'Privacyverklaring', '/voorwaarden' => 'Algemene voorwaarden', '/' => 'Wedstrijdplanning zonder puzzelstress', '/functies' => 'Alles wat de zaalcommissie doet', '/prijzen' => 'Eén prijs per seizoen', '/contact' => 'Plan een demo of stel je vraag'] as $path => $heading) {
            $this->get($path)->assertOk()->assertSee($heading);
        }
    }

    public function test_an_empty_setting_is_left_out_instead_of_shown_as_placeholder(): void
    {
        config(['site.phone' => null]);

        $this->get('/prijzen')->assertOk()->assertSee('Op aanvraag')->assertDontSee('[PRIJS]');
        $this->get('/contact')->assertOk()->assertDontSee('tel:');
    }

    public function test_the_pricing_page_shows_the_public_plans_from_the_database(): void
    {
        Plan::query()->where('slug', 'basis')->update(['price' => 249, 'max_teams' => 12]);
        Plan::factory()->create(['name' => 'Verborgen pakket', 'is_public' => false]);

        $this->get('/prijzen')
            ->assertOk()
            ->assertSeeInOrder(['Basis', 'Vereniging', 'Groot'])
            ->assertSee('€249')
            ->assertSee('tot 12')
            ->assertDontSee('Verborgen pakket');
    }

    public function test_the_upgrade_topic_is_not_offered_on_the_public_form(): void
    {
        $this->get('/contact')->assertDontSee('value="upgrade"', false);
        $this->post('/contact', [...$this->validRequest(), 'topic' => 'upgrade'])->assertSessionHasErrors('topic');
    }

    public function test_a_demo_request_is_stored_and_mailed(): void
    {
        Notification::fake();
        config(['site.contact_recipient' => 'sales@example.test']);

        $this->post('/contact', $this->validRequest())
            ->assertRedirect('/contact')
            ->assertSessionHas('contact-sent', 'demo');

        $request = ContactRequest::sole();
        $this->assertSame(ContactTopic::Demo, $request->topic);
        $this->assertSame('VC Voorbeeld', $request->club);
        Notification::assertSentOnDemand(ContactRequestReceived::class);
    }

    public function test_the_privacy_consent_is_required_and_bots_are_turned_away(): void
    {
        $this->post('/contact', ['privacy' => null] + $this->validRequest())->assertSessionHasErrors('privacy');
        $this->post('/contact', ['website' => 'https://spam.example'] + $this->validRequest())->assertSessionHasErrors('website');

        $this->assertSame(0, ContactRequest::count());
    }

    public function test_platform_admins_handle_requests_in_the_platform_panel(): void
    {
        $request = ContactRequest::create(['topic' => 'vraag'] + collect($this->validRequest())->only(['name', 'club', 'email'])->all());
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');

        Livewire::test(ListContactRequests::class)
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('handle', $request);

        $this->assertNotNull($request->refresh()->handled_at);
    }

    /** @return array<string, string> */
    private function validRequest(): array
    {
        return [
            'topic' => 'demo',
            'name' => 'Sanne de Vries',
            'club' => 'VC Voorbeeld',
            'email' => 'sanne@example.test',
            'teams' => '15 tot 30',
            'venues' => '2 of 3',
            'message' => 'We plannen nu in Excel.',
            'privacy' => '1',
        ];
    }
}
