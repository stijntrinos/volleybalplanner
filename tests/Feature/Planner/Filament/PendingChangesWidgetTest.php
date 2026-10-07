<?php

namespace Tests\Feature\Planner\Filament;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Widgets\PendingAwayChanges;
use App\Planner\Filament\Widgets\PendingChanges;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/** Ported from Aetos LopendeWijzigingenWidgetTest; the site flag became the module switches. */
class PendingChangesWidgetTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private User $user;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('planner:sync-roles')->assertSuccessful();

        $this->club = $this->createClub();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->clubs()->attach($this->club);
        setPermissionsTeamId($this->club->getKey());

        $this->actingAs($this->user);
        $this->useClub($this->club);

        $this->version = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Published]);
    }

    public function test_only_shows_matches_with_a_running_change(): void
    {
        $this->grantRole();

        $requested = $this->match(['change_status' => ChangeStatus::Requested]);
        $olderFlag = $this->match(['change_status' => null, 'change_requested' => true]);

        $awayInDiscussion = $this->match(['match_type' => MatchType::Away, 'away_change_status' => AwayChangeStatus::InDiscussion]);
        $awayInProgress = $this->match(['match_type' => MatchType::Away, 'away_change_status' => AwayChangeStatus::InProgress]);
        $awayWithGeneralFlag = $this->match(['match_type' => MatchType::Away, 'change_status' => ChangeStatus::Requested]);
        $finished = $this->match(['match_type' => MatchType::Away, 'away_change_status' => AwayChangeStatus::Final]);
        $quiet = $this->match([]);

        Livewire::test(PendingChanges::class)
            ->assertCanSeeTableRecords([$requested, $olderFlag])
            ->assertCanNotSeeTableRecords([$awayInDiscussion, $awayInProgress, $awayWithGeneralFlag, $finished, $quiet]);
    }

    public function test_away_matches_with_a_running_change_have_their_own_widget(): void
    {
        $this->grantRole();

        $inDiscussion = $this->match([
            'match_type' => MatchType::Away,
            'away_change_status' => AwayChangeStatus::InDiscussion,
            'away_new_date' => '2026-10-03',
            'away_new_time' => '19:30',
        ]);
        $inProgress = $this->match(['match_type' => MatchType::Away, 'away_change_status' => AwayChangeStatus::InProgress]);
        $final = $this->match(['match_type' => MatchType::Away, 'away_change_status' => AwayChangeStatus::Final]);
        $home = $this->match(['change_status' => ChangeStatus::Requested]);

        Livewire::test(PendingAwayChanges::class)
            ->assertCanSeeTableRecords([$inDiscussion, $inProgress])
            ->assertCanNotSeeTableRecords([$final, $home])
            ->assertSee('In overleg')
            ->assertSee('Nieuw: 03-10-2026 · 19:30');
    }

    public function test_the_away_widget_follows_the_rights_of_pending_changes(): void
    {
        $this->assertFalse(PendingAwayChanges::canView());

        $this->grantRole();

        $this->assertTrue(PendingAwayChanges::canView());
    }

    public function test_the_widget_is_hidden_without_the_permission(): void
    {
        $this->assertFalse(PendingChanges::canView());

        $this->grantRole();

        $this->assertTrue(PendingChanges::canView());
    }

    public function test_the_away_widget_is_hidden_without_the_away_matches_module(): void
    {
        $this->grantRole();
        $this->club->update(['features' => array_values(array_diff(PlannerFeature::allValues(), [PlannerFeature::AwayMatches->value]))]);
        $this->useClub($this->club->fresh());

        $this->assertTrue(PendingChanges::canView());
        $this->assertFalse(PendingAwayChanges::canView());
    }

    public function test_a_nevobo_change_shows_exactly_what_was_updated_automatically(): void
    {
        $this->grantRole();

        $match = $this->match([
            'nevobo_change_status' => NevoboChangeStatus::Applied,
            'nevobo_changed_at' => now(),
            'nevobo_previous_date' => '2026-09-12',
            'nevobo_previous_time' => '15:00',
            'nevobo_previous_venue' => 'Middachtensingel',
            'nevobo_new_date' => '2026-09-19',
            'nevobo_new_time' => '17:00',
            'nevobo_new_venue' => 'Middachtensingel',
        ]);

        Livewire::test(PendingChanges::class)
            ->assertCanSeeTableRecords([$match])
            ->assertSee('Automatisch bijgewerkt')
            ->assertSee('datum: 12-09-2026 -&gt; 19-09-2026', escape: false)
            ->assertSee('Tijd: 15:00 -&gt; 17:00', escape: false)
            ->assertDontSee('Sporthal:');
    }

    private function grantRole(): void
    {
        $this->user->assignClubRole($this->club, ClubRole::PlanningCommittee);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** @param array<string, mixed> $values */
    private function match(array $values): FixtureMatch
    {
        return FixtureMatch::factory()->create($values + [
            'version_id' => $this->version->id,
            'date' => '2026-09-12',
            'time' => '15:00',
            'match_type' => MatchType::Home,
        ]);
    }
}
