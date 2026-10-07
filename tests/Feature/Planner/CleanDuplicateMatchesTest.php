<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class CleanDuplicateMatchesTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->club = $this->useClub($this->createClub());
        $this->version = ScheduleVersion::factory()->create();
    }

    public function test_the_rss_copy_disappears_and_the_federation_match_stays(): void
    {
        $real = $this->match('H3D-GA', MatchType::Home);
        $copy = $this->match('RSS-5FAD15CC1729EB48', MatchType::Away);

        $this->artisan("planner:clean-duplicate-matches --club={$this->club->slug} --delete")
            ->expectsConfirmation('1 kopieën verwijderen?', 'yes')
            ->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($real->id));
        $this->assertNull(FixtureMatch::query()->find($copy->id));
    }

    public function test_without_the_flag_nothing_is_deleted(): void
    {
        $copy = $this->match('RSS-5FAD15CC1729EB48', MatchType::Away);
        $this->match('H3D-GA', MatchType::Home);

        $this->artisan("planner:clean-duplicate-matches --club={$this->club->slug}")->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($copy->id));
    }

    public function test_two_matches_with_a_real_code_both_stay(): void
    {
        $first = $this->match('H3D-GA', MatchType::Home);
        $second = $this->match('H3D-GB', MatchType::Away);

        $this->artisan("planner:clean-duplicate-matches --club={$this->club->slug} --delete --no-interaction")->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($first->id));
        $this->assertNotNull(FixtureMatch::query()->find($second->id));
    }

    public function test_a_copy_with_its_own_assignment_stays(): void
    {
        $this->match('H3D-GA', MatchType::Home);
        $copy = $this->match('RSS-5FAD15CC1729EB48', MatchType::Away, ['scorer' => 'DS 8']);

        $this->artisan("planner:clean-duplicate-matches --club={$this->club->slug} --delete --no-interaction")->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($copy->id));
    }

    public function test_the_same_teams_on_another_day_are_no_copy(): void
    {
        $this->match('H3D-GA', MatchType::Home);
        $return = $this->match('RSS-5FAD15CC1729EB48', MatchType::Away, ['date' => '2026-11-14']);

        $this->artisan("planner:clean-duplicate-matches --club={$this->club->slug} --delete --no-interaction")->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($return->id));
    }

    public function test_all_clubs_are_cleaned_each_on_their_own(): void
    {
        $other = $this->createClub();
        $otherCopy = app(CurrentClub::class)->run($other, function (): FixtureMatch {
            $version = ScheduleVersion::factory()->create();
            $this->match('H3D-GA', MatchType::Home, ['version_id' => $version->id]);

            return $this->match('RSS-5FAD15CC1729EB48', MatchType::Away, ['version_id' => $version->id]);
        });
        // A synthetic match in this club that only resembles the other club's real one.
        $lonely = $this->match('RSS-5FAD15CC1729EB48', MatchType::Away);

        $this->artisan('planner:clean-duplicate-matches --all --delete --no-interaction')->assertSuccessful();

        $this->assertNull(FixtureMatch::withoutGlobalScopes()->find($otherCopy->id));
        $this->assertNotNull(FixtureMatch::query()->find($lonely->id), 'a real code of another club makes no copy here');
    }

    public function test_pool_matches_without_own_team_are_removed_per_club(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $own = $this->match('H3D-GA', MatchType::Home);
        $foreign = $this->match('VREEMD1', MatchType::Away, ['home_team_name' => 'Gemini JA 1', 'away_team_name' => 'Toesj JA 1']);
        // Another club without patterns: nothing is recognised as its own, so nothing is removed.
        $otherForeign = app(CurrentClub::class)->run($this->createClub(), fn (): FixtureMatch => FixtureMatch::factory()->create([
            'version_id' => ScheduleVersion::factory(), 'home_team_name' => 'Gemini JA 1', 'away_team_name' => 'Toesj JA 1',
        ]));

        $this->artisan('planner:clean-pool-matches --all --delete --no-interaction')->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($own->id));
        $this->assertNull(FixtureMatch::query()->find($foreign->id));
        $this->assertNotNull(FixtureMatch::withoutGlobalScopes()->find($otherForeign->id));
    }

    public function test_pool_matches_are_only_reported_without_the_flag(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $foreign = $this->match('VREEMD1', MatchType::Away, ['home_team_name' => 'Gemini JA 1', 'away_team_name' => 'Toesj JA 1']);

        $this->artisan("planner:clean-pool-matches --club={$this->club->slug}")->assertSuccessful();

        $this->assertNotNull(FixtureMatch::query()->find($foreign->id));
    }

    /** @param  array<string, mixed>  $extra */
    private function match(string $code, MatchType $type, array $extra = []): FixtureMatch
    {
        return FixtureMatch::factory()->create($extra + [
            'version_id' => $this->version->id, 'match_code' => $code,
            'date' => '2026-10-29', 'time' => '20:30',
            'home_team_name' => 'AETOS HS 9', 'away_team_name' => 'VoCASA HS 6',
            'match_type' => $type, 'referee' => null, 'scorer' => null,
        ]);
    }
}
