<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\MatchOverview;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Tenancy, module gating, framing and branding of the public club pages. */
class PublicClubPagesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private const HTML_PAGES = ['', '/wedstrijden', '/wedstrijd-verzetten', '/dienst-wijzigen'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-09-01 12:00');
    }

    public function test_a_clubs_public_pages_never_show_another_clubs_matches(): void
    {
        $clubA = $this->clubWithMatch('CODE-OF-A', 'Alpha DS 1');
        $clubB = $this->clubWithMatch('CODE-OF-B', 'Bravo DS 1');

        $this->get("/{$clubA->slug}/wedstrijden")->assertOk()->assertSee('CODE-OF-A')->assertDontSee('CODE-OF-B');
        $this->get("/{$clubB->slug}/wedstrijden")->assertOk()->assertSee('CODE-OF-B')->assertDontSee('CODE-OF-A');
        $this->get("/{$clubA->slug}/rss/thuis")->assertOk()->assertSee('CODE-OF-A')->assertDontSee('CODE-OF-B');
        $this->get("/{$clubA->slug}/rss")->assertOk()->assertSee('CODE-OF-A')->assertDontSee('CODE-OF-B');
        $this->get("/{$clubA->slug}/wedstrijd-verzetten")->assertOk()->assertSee('Alpha DS 1')->assertDontSee('Bravo DS 1');
    }

    public function test_livewire_updates_restore_the_club_of_the_page(): void
    {
        $clubA = $this->clubWithMatch('CODE-OF-A', 'Alpha DS 1');
        $this->clubWithMatch('CODE-OF-B', 'Bravo DS 1');
        $this->useClub($clubA);

        $component = Livewire::test(MatchOverview::class)->assertSet('clubId', $clubA->id);

        // A Livewire update request has no club middleware and no current club.
        app(CurrentClub::class)->set(null);

        $component->set('search', 'ds 1')->assertSee('CODE-OF-A')->assertDontSee('CODE-OF-B');
        $this->assertSame($clubA->id, app(CurrentClub::class)->id());
    }

    public function test_the_club_id_of_a_component_cannot_be_changed_from_the_browser(): void
    {
        $clubA = $this->clubWithMatch('CODE-OF-A', 'Alpha DS 1');
        $clubB = $this->clubWithMatch('CODE-OF-B', 'Bravo DS 1');
        $this->useClub($clubA);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(MatchOverview::class)->set('clubId', $clubB->id);
    }

    public function test_disabled_modules_answer_404(): void
    {
        $club = $this->useClub($this->createClub([]));

        $this->get("/{$club->slug}/wedstrijden")->assertOk();
        $this->get("/{$club->slug}/wedstrijd-verzetten")->assertNotFound();
        $this->get("/{$club->slug}/dienst-wijzigen")->assertNotFound();
        $this->get("/{$club->slug}/rss")->assertNotFound();
        $this->get("/{$club->slug}/rss/thuis")->assertNotFound();
    }

    public function test_an_inactive_or_unknown_club_answers_404(): void
    {
        $club = $this->useClub($this->createClub());
        $club->update(['is_active' => false]);

        foreach ([...self::HTML_PAGES, '/rss', '/rss/thuis'] as $path) {
            $this->get("/{$club->slug}{$path}")->assertNotFound();
        }

        $this->get('/club-that-does-not-exist/wedstrijden')->assertNotFound();
    }

    public function test_the_landing_page_links_only_to_enabled_pages(): void
    {
        $club = $this->useClub($this->createClub([PlannerFeature::ChangeRequests->value]));

        $this->get("/{$club->slug}")
            ->assertOk()
            ->assertSee($club->name)
            ->assertSee(route('planner.public.matches', ['club' => $club]), false)
            ->assertSee(route('planner.public.match-change-request', ['club' => $club]), false)
            ->assertDontSee('/dienst-wijzigen', false)
            ->assertDontSee('/rss', false);
    }

    public function test_public_pages_are_indexable_with_a_canonical_url_except_for_demo_clubs(): void
    {
        $club = $this->useClub($this->createClub());

        $this->get("/{$club->slug}/wedstrijden?team=HS1")->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('planner.public.matches', ['club' => $club]).'">', false)
            ->assertDontSee('noindex', false);

        $club->update(['is_demo' => true]);

        $this->get("/{$club->slug}/wedstrijden")->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('rel="canonical"', false);
    }

    public function test_normal_pages_keep_the_framing_protection(): void
    {
        $club = $this->useClub($this->createClub());

        foreach (self::HTML_PAGES as $path) {
            $this->get("/{$club->slug}{$path}")
                ->assertOk()
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
                ->assertSee('class="planner-header"', false)
                ->assertSee('Gepland met')
                ->assertSee('<strong>'.e(config('app.name')).'</strong>', false);
        }
    }

    public function test_embed_mode_hides_header_and_footer_and_allows_framing(): void
    {
        $club = $this->useClub($this->createClub());

        foreach (self::HTML_PAGES as $path) {
            $this->get("/{$club->slug}{$path}?embed=1")
                ->assertOk()
                ->assertHeaderMissing('X-Frame-Options')
                ->assertHeader('Content-Security-Policy', 'frame-ancestors *')
                ->assertDontSee('class="planner-header"', false)
                ->assertDontSee('Gepland met');
        }
    }

    public function test_the_layout_uses_the_club_colour_and_logo(): void
    {
        Storage::fake('public');
        $club = $this->useClub($this->createClub());
        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'public');
        PlannerSetting::current()->update(['primary_color' => '#123abc', 'logo_path' => $path]);

        $this->get("/{$club->slug}/wedstrijden")
            ->assertOk()
            ->assertSee('--planner-primary: #123abc', false)
            ->assertSee(Storage::disk('public')->url($path), false)
            // The name stands next to the logo, so the image itself is decorative.
            ->assertSee('<span class="planner-club-name">'.e($club->name).'</span>', false);
    }

    public function test_an_invalid_colour_falls_back_and_the_name_is_shown(): void
    {
        $club = $this->useClub($this->createClub());
        PlannerSetting::current()->update(['primary_color' => 'red;}body{display:none']);

        $this->get("/{$club->slug}/wedstrijden")
            ->assertOk()
            ->assertDontSee('display:none', false)
            ->assertSee('<span class="planner-club-name">'.e($club->name).'</span>', false);
    }

    public function test_the_output_never_mentions_aetos(): void
    {
        $club = $this->clubWithMatch('CODE-OF-A', 'Alpha DS 1');

        foreach ([...self::HTML_PAGES, '/rss', '/rss/thuis', '/rss/dag/2026-09-12', '/rss/team/DS1', '/rss/sporthal/x'] as $path) {
            foreach (['', '?embed=1'] as $query) {
                $content = (string) $this->get("/{$club->slug}{$path}{$query}")->assertOk()->getContent();
                $this->assertStringNotContainsStringIgnoringCase('aetos', $content, "{$path}{$query} mentions Aetos");
            }
        }
    }

    private function clubWithMatch(string $code, string $teamName): Club
    {
        $club = $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => [strtok($teamName, ' ')]]);
        $team = Team::factory()->create(['code' => 'DS 1', 'name' => $teamName]);
        $version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published, 'is_published' => true]);
        FixtureMatch::factory()->create([
            'version_id' => $version->id, 'date' => '2026-09-12', 'time' => '10:00', 'match_code' => $code,
            'home_team_name' => $teamName, 'home_team_id' => $team->id,
        ]);

        return $club;
    }
}
