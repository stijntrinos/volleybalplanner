<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Models\Club;
use App\Models\Invitation;
use App\Models\User;
use App\Planner\Enums\MatchType;
use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\Nevobo\NevoboClubLinker;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\CurrentClub;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\FakesNevobo;
use Tests\TestCase;

class AdminDemoClubTest extends TestCase
{
    use FakesNevobo, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
    }

    /** Registered before the Nevobo API fake, so the programme feeds win over its 404. */
    private function fakeProgrammes(int $status = 200): void
    {
        Http::fake(function (Request $request) use ($status) {
            if (!str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/programma.rss')) {
                return null;
            }

            return Http::response(<<<'XML'
                <rss><channel>
                <item><title><![CDATA[AETOS HS 1 - Scylla HS 2]]></title><description><![CDATA[Wedstrijd: 3CH IE, 21-11-2026 17:00, Speellocatie: Sporthal Middachtensingel, Middachtensingel 4, Arnhem]]></description></item>
                <item><title><![CDATA[AETOS DS 1 - Volga DS 1]]></title><description><![CDATA[Wedstrijd: 3CD AB, 21-11-2026 17:00, Speellocatie: Sporthal Middachtensingel, Middachtensingel 4, Arnhem]]></description></item>
                <item><title><![CDATA[Volga HS 1 - AETOS HS 1]]></title><description><![CDATA[Wedstrijd: 3CH CD, 28-11-2026 15:00, Speellocatie: de Pol, Pelgrimstraat 61, Gaanderen]]></description></item>
                </channel></rss>
                XML, $status);
        });
        $this->fakeNevobo();
    }

    public function test_a_demo_is_filled_with_the_teams_and_programme_of_a_nevobo_club(): void
    {
        $this->fakeProgrammes();

        Livewire::test(ListClubs::class)
            ->mountAction('createDemo')
            ->fillForm(['source' => 'nevobo', 'club_code' => 'CNH8Q1U', 'email' => 'Jan@Example.test', 'contact_name' => 'Jan'])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified();

        $club = Club::query()->where('is_demo', true)->sole();
        $this->assertSame('AETOS (demo)', $club->name);
        $this->assertSame('groot', $club->plan?->slug);

        app(CurrentClub::class)->run($club, function (): void {
            $this->assertSame(['HS 1', 'DS 1', 'DS 11', 'MA 1', 'JC 1', 'XZ 1'], Team::query()->orderBy('id')->pluck('code')->all());

            // Only the hall of the home matches; both play at 17:00, so two courts.
            $venue = Venue::query()->sole();
            $this->assertSame('Sporthal Middachtensingel', $venue->name);
            $this->assertTrue($venue->has_duties);
            $this->assertSame(2, $venue->courts()->count());
            $this->assertSame($venue->id, PlannerSetting::current()->main_venue_id);
            $this->assertSame($venue->id, Team::query()->where('code', 'HS 1')->value('default_venue_id'));

            $home = FixtureMatch::query()->where('match_type', MatchType::Home)->with('court')->get();
            $this->assertCount(2, $home);
            $this->assertEqualsCanonicalizing(['1', '2'], $home->pluck('court.name')->all());
            $this->assertSame(1, FixtureMatch::query()->where('match_type', MatchType::Away)->count());
        });

        $invitation = Invitation::query()->where('club_id', $club->id)->sole();
        $this->assertSame('jan@example.test', $invitation->email);
        $this->assertSame(ClubRole::ClubAdmin, $invitation->role);
    }

    public function test_a_demo_keeps_its_teams_when_the_programme_cannot_be_read(): void
    {
        $this->fakeProgrammes(status: 500);

        Livewire::test(ListClubs::class)
            ->callAction(TestAction::make('createDemo'), ['source' => 'nevobo', 'club_code' => 'CNH8Q1U', 'name' => 'Aetos'])
            ->assertHasNoFormErrors();

        $club = Club::query()->where('is_demo', true)->sole();
        $this->assertSame('Aetos (demo)', $club->name);
        $this->assertSame(6, app(CurrentClub::class)->run($club, fn (): int => Team::query()->count()));
    }

    public function test_no_club_is_left_when_nevobo_is_unreachable(): void
    {
        $this->fakeNevobo();
        $this->mock(NevoboClubLinker::class)->shouldReceive('link')->andThrow(new NevoboUnavailable('Time-out'));

        Livewire::test(ListClubs::class)
            ->callAction(TestAction::make('createDemo'), ['source' => 'nevobo', 'club_code' => 'CNH8Q1U', 'email' => 'jan@example.test'])
            ->assertNotified(__('Nevobo niet bereikbaar'));

        $this->assertSame(0, Club::query()->count());
        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_a_demo_with_example_data_needs_a_name(): void
    {
        Livewire::test(ListClubs::class)
            ->callAction(TestAction::make('createDemo'), ['source' => 'example'])
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::test(ListClubs::class)
            ->callAction(TestAction::make('createDemo'), ['source' => 'example', 'name' => 'VV De Smash'])
            ->assertHasNoFormErrors();

        $club = Club::query()->sole();
        $this->assertSame('VV De Smash (demo)', $club->name);
        $this->assertTrue($club->is_demo);
        $this->assertSame(24, app(CurrentClub::class)->run($club, fn (): int => Team::query()->count()));
        $this->assertSame(0, Invitation::query()->count());
    }
}
