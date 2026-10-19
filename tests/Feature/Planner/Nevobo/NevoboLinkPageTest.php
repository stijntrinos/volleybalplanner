<?php

namespace Tests\Feature\Planner\Nevobo;

use App\Planner\Filament\Pages\NevoboLink;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Support\ClubRole;
use App\Planner\Support\CommitteeRole;
use App\Planner\Support\PlannerPermissions;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Concerns\FakesNevobo;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class NevoboLinkPageTest extends TestCase
{
    use FakesNevobo, InteractsWithClubs, RefreshDatabase;

    public function test_picking_a_club_preselects_regular_teams_and_linking_creates_them(): void
    {
        $this->fakeNevobo();
        $this->actingAsClubUser();
        Team::factory()->create(['code' => 'DS 11']);

        $page = Livewire::test(NevoboLink::class)
            ->set('data.club_code', 'CNH8Q1U');

        $this->assertSame([
            '/competitie/teams/cnh8q1u/heren/1', '/competitie/teams/cnh8q1u/dames/1', '/competitie/teams/cnh8q1u/dames/11',
            '/competitie/teams/cnh8q1u/meiden-a/1', '/competitie/teams/cnh8q1u/jongens-c/1', '/competitie/teams/cnh8q1u/mix-zitvolleybal/1',
        ], $page->get('data.team_ids'));
        $page->assertSee('AETOS Plauti DS 1')->assertSee('bestaat al');

        $page->call('link')->assertNotified(__('Gekoppeld aan de Nevobo'));

        $this->assertEqualsCanonicalizing(['HS 1', 'DS 1', 'DS 11', 'MA 1', 'JC 1', 'XZ 1'], Team::query()->pluck('code')->all());
        $this->assertSame(6, TeamSource::query()->forImport()->count());
        $this->assertSame('CNH8Q1U', PlannerSetting::current()->nevobo_club_code);
    }

    public function test_reopening_a_linked_club_only_ticks_the_teams_it_has(): void
    {
        $this->fakeNevobo();
        $this->actingAsClubUser();
        Team::factory()->create(['code' => 'HS 1']);
        PlannerSetting::current()->update(['nevobo_club_code' => 'CNH8Q1U']);

        Livewire::test(NevoboLink::class)
            ->assertSet('data.club_code', 'CNH8Q1U')
            ->assertSet('data.team_ids', ['/competitie/teams/cnh8q1u/heren/1'])
            ->assertSee('Gekoppeld aan Nevobo-vereniging CNH8Q1U');
    }

    public function test_an_unreachable_nevobo_shows_a_friendly_error(): void
    {
        Sleep::fake();
        Http::fake(['api.nevobo.nl/*' => Http::response('', 500)]);
        $this->actingAsClubUser();

        Livewire::test(NevoboLink::class)
            ->set('data.club_code', 'CNH8Q1U')
            ->assertNotified(Notification::make()->title(__('Nevobo niet bereikbaar'))
                ->body(__('De Nevobo is tijdelijk niet beschikbaar (HTTP :status). Probeer het later opnieuw.', ['status' => 500]))->danger())
            ->assertSet('data.team_ids', []);
    }

    public function test_the_page_belongs_to_the_master_data_group(): void
    {
        $club = $this->createClub();
        CommitteeRole::update($club, array_values(array_diff(PlannerPermissions::groups(), ['master_data'])));

        $this->actingAsClubUser(ClubRole::PlanningCommittee, $club);

        $this->get('/app/'.$club->slug.'/nevobo-link')->assertForbidden();
    }
}
