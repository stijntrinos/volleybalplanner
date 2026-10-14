<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\OverlapPolicy;
use App\Planner\Features\PlannerFeature;
use App\Planner\Filament\Pages\Settings;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\UserPreference;
use App\Planner\Services\Duties\DutyRules;
use App\Planner\Support\ClubRole;
use App\Planner\Support\ClubTeamMatcher;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

/**
 * The duty rules are stored as a map (team => allowed teams) and edited as a
 * numbered list in the repeater. If that translation goes wrong, the club's
 * rules silently disappear the next time another field is saved.
 */
class SettingsPageTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Club $club;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsClubUser();
        $this->club = app(CurrentClub::class)->require();
    }

    public function test_without_stored_rules_the_form_shows_the_defaults(): void
    {
        $data = Livewire::test(Settings::class)->get('data');

        $this->assertSame([], $data['duty_rules']['no_referee']);
        $this->assertSame([], array_values($data['duty_rules']['referee_requirements']));
        $this->assertSame('19:00', $data['duty_rules']['scorer_cutoff']);
        $this->assertSame(DutyRules::DEFAULTS['hall_duty_codes'], $data['duty_rules']['hall_duty_codes']);
    }

    public function test_the_referee_requirements_survive_saving_and_reopening(): void
    {
        $this->filledForm()
            ->set('data.duty_rules.no_referee', ['DS 8'])
            ->set('data.duty_rules.referee_requirements', [['team' => 'HS 5', 'allowed' => ['DS 1', 'HS 1']]])
            ->set('data.duty_rules.scorer_cutoff', '20:00')
            ->call('save')
            ->assertHasNoErrors();

        $saved = PlannerSetting::current()->fresh();
        $this->assertSame(['DS 8'], $saved->duty_rules['no_referee']);
        $this->assertSame(['HS 5' => ['DS 1', 'HS 1']], $saved->duty_rules['referee_requirements']);

        $rules = new DutyRules($saved->duty_rules);
        $this->assertTrue($rules->mayNotReferee('DS8'));
        $this->assertFalse($rules->mayNotReferee('DS10'));
        $this->assertSame(['DS1', 'HS1'], $rules->refereeRequirement('HS5'));
        $this->assertSame(1200, $rules->scorerCutoffMinutes());

        // Reopening gives the same rows as were filled in.
        $data = Livewire::test(Settings::class)->get('data');
        $this->assertSame([['team' => 'HS 5', 'allowed' => ['DS 1', 'HS 1']]], array_values($data['duty_rules']['referee_requirements']));
    }

    public function test_competition_rules_are_typed_day_month_and_stored_month_day(): void
    {
        $this->filledForm()
            ->set('data.youth_spring_start', '01-01')
            ->set('data.youth_spring_end', '30-04')
            ->set('data.internal_senior_deadline', '01-03')
            ->call('save')
            ->assertHasNoErrors();

        $saved = PlannerSetting::current()->fresh();
        $this->assertSame(['01-01', '04-30', '03-01'], [$saved->youth_spring_start, $saved->youth_spring_end, $saved->internal_senior_deadline]);
        $this->assertSame('30-04', Livewire::test(Settings::class)->get('data.youth_spring_end'));

        $this->filledForm()->set('data.youth_spring_end', '31-13')->call('save')->assertHasErrors(['data.youth_spring_end']);
        $this->filledForm()->set('data.internal_senior_deadline', '')->call('save')->assertHasNoErrors();
        $this->assertNull(PlannerSetting::current()->fresh()->internal_senior_deadline);
    }

    public function test_an_emptied_list_stays_empty(): void
    {
        PlannerSetting::current()->update(['duty_rules' => ['no_referee' => ['DS 10']]]);

        $this->filledForm()
            ->set('data.duty_rules.no_referee', [])
            ->call('save')
            ->assertHasNoErrors();

        $saved = PlannerSetting::current()->fresh();
        $this->assertSame([], $saved->duty_rules['no_referee']);
        $this->assertFalse((new DutyRules($saved->duty_rules))->mayNotReferee('DS10'));
    }

    public function test_without_the_right_the_club_settings_do_not_change(): void
    {
        $this->giveOnlyPermissions(['View:Settings']);
        PlannerSetting::current()->update(['buffer_minutes' => 15]);

        Livewire::test(Settings::class)
            ->set('data.buffer_minutes', 90)
            ->set('data.duty_rules.no_referee', ['DS 1'])
            ->call('save');

        $saved = PlannerSetting::current()->fresh();
        $this->assertSame(15, $saved->buffer_minutes);
        $this->assertNull($saved->duty_rules);
        // The own preferences are saved.
        $this->assertSame(1, UserPreference::query()->where('user_id', $this->user->id)->count());
    }

    public function test_activity_colors_are_updated_and_removed_via_the_colors_tab(): void
    {
        ActivityColor::query()->create(['type' => 'Feestdag', 'color' => '#ff0000']);
        ActivityColor::query()->create(['type' => 'Bekerronde', 'color' => '#00ff00']);

        $component = Livewire::test(Settings::class);
        $this->assertSame(
            [['type' => 'Bekerronde', 'color' => '#00ff00'], ['type' => 'Feestdag', 'color' => '#ff0000']],
            array_values($component->get('data')['activity_colors']),
        );

        // Feestdag gets another colour, Bekerronde disappears from the list.
        $this->fill($component)
            ->set('data.activity_colors', [
                ['type' => 'Feestdag', 'color' => '#123456'],
                ['type' => 'Toernooi', 'color' => '#abcdef'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(
            ['Feestdag' => '#123456', 'Toernooi' => '#abcdef'],
            ActivityColor::query()->orderBy('type')->pluck('color', 'type')->all(),
        );
    }

    public function test_colors_of_another_club_stay(): void
    {
        $other = $this->createClub();
        app(CurrentClub::class)->run($other, fn () => ActivityColor::query()->create(['type' => 'Feestdag', 'color' => '#ff0000']));

        $this->filledForm()
            ->set('data.activity_colors', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, ActivityColor::withoutGlobalScopes()->where('club_id', $other->id)->count());
    }

    public function test_colors_follow_their_own_right_and_not_that_of_the_settings(): void
    {
        $this->giveOnlyPermissions(['View:Settings', 'ViewAny:ActivityColor', 'Update:ActivityColor']);
        PlannerSetting::current()->update(['buffer_minutes' => 15]);

        $this->filledForm()
            ->set('data.buffer_minutes', 90)
            ->set('data.activity_colors', [['type' => 'Feestdag', 'color' => '#123456']])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15, PlannerSetting::current()->fresh()->buffer_minutes);
        $this->assertSame(['Feestdag' => '#123456'], ActivityColor::query()->pluck('color', 'type')->all());
    }

    public function test_without_the_color_right_the_colors_stay_untouched(): void
    {
        $this->giveOnlyPermissions(['View:Settings', 'Update:PlannerSetting']);
        ActivityColor::query()->create(['type' => 'Feestdag', 'color' => '#ff0000']);

        $this->filledForm()->call('save')->assertHasNoErrors();

        // The tab is not in the form; the cleanup must not delete anything.
        $this->assertSame(['Feestdag' => '#ff0000'], ActivityColor::query()->pluck('color', 'type')->all());
    }

    public function test_the_branding_fields_and_team_name_patterns_are_saved(): void
    {
        Storage::fake('public');

        $this->filledForm()
            ->set('data.team_name_patterns', ['VV Demo', ' Demo ', ''])
            ->set('data.logo_path', UploadedFile::fake()->image('logo.png', 120, 120))
            ->set('data.primary_color', '#123abc')
            ->set('data.schedule_tagline', 'Samen sportief')
            ->set('data.schedule_footer', 'Meld je bij de zaalwacht')
            ->call('save')
            ->assertHasNoErrors();

        $saved = PlannerSetting::current()->fresh();
        $this->assertSame(['VV Demo', 'Demo'], $saved->team_name_patterns);
        $this->assertSame('#123abc', $saved->primary_color);
        $this->assertSame('Samen sportief', $saved->schedule_tagline);
        $this->assertSame('Meld je bij de zaalwacht', $saved->schedule_footer);
        $this->assertNotNull($saved->logo_path);
        $this->assertStringStartsWith('planner/'.$this->club->id.'/branding/', $saved->logo_path);
        Storage::disk('public')->assertExists($saved->logo_path);
    }

    public function test_an_invalid_primary_color_is_rejected(): void
    {
        $this->filledForm()
            ->set('data.primary_color', 'red; background:url(x)')
            ->call('save')
            ->assertHasErrors(['data.primary_color']);
    }

    public function test_changed_team_name_patterns_immediately_change_the_team_matcher(): void
    {
        PlannerSetting::current()->update(['team_name_patterns' => ['Orion']]);
        $this->assertTrue(ClubTeamMatcher::forCurrentClub()->isOwnTeam('Orion HS 1'));
        $this->assertFalse(ClubTeamMatcher::forCurrentClub()->isOwnTeam('VV Demo HS 1'));

        $this->filledForm()
            ->set('data.team_name_patterns', ['VV Demo'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(ClubTeamMatcher::forCurrentClub()->isOwnTeam('VV Demo HS 1'));
        $this->assertFalse(ClubTeamMatcher::forCurrentClub()->isOwnTeam('Orion HS 1'));
    }

    public function test_module_sections_are_hidden_when_the_module_is_off(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub([]));
        PlannerSetting::current()->update(['points_referee_duty' => 7, 'duty_rules' => ['no_referee' => ['DS 1']]]);

        $component = Livewire::test(Settings::class)
            ->assertDontSee('Taken & punten')
            ->assertDontSee('Wijzigingsverzoeken')
            ->assertDontSee('Trainingenperiode')
            ->assertDontSee('Losse dagen zonder zaaldienst')
            ->assertSee('Teamnamen van je eigen club beginnen met');

        $this->fill($component, withModules: false)->call('save')->assertHasNoErrors();

        // Hidden fields are not saved, so the stored values stay.
        $saved = PlannerSetting::current()->fresh();
        $this->assertSame(7, $saved->points_referee_duty);
        $this->assertSame(['no_referee' => ['DS 1']], $saved->duty_rules);
    }

    public function test_the_point_fields_follow_volunteer_points_and_volleystars(): void
    {
        $this->actingAsClubUser(ClubRole::PlanningCommittee, $this->createClub([PlannerFeature::Duties->value]));

        Livewire::test(Settings::class)
            ->assertSee('Wie mag welke taak')
            ->assertDontSee('Vrijwilligerspunten per taak');
    }

    private function filledForm(): Testable
    {
        return $this->fill(Livewire::test(Settings::class));
    }

    private function fill(Testable $component, bool $withModules = true): Testable
    {
        $component
            ->set('data.overlap_policy', OverlapPolicy::Warn->value)
            ->set('data.buffer_minutes', 15)
            ->set('data.cache_ttl_minutes', 15)
            ->set('data.schedule_footer', 'GRAAG MELDEN BIJ DE ZAALWACHT!');

        if ($withModules) {
            $component
                ->set('data.points_referee_duty', 0)
                ->set('data.points_scorer_duty', 0)
                ->set('data.points_hall_duty', 0)
                ->set('data.points_volleystars', 0);
        }

        return $component;
    }

    /** @param list<string> $permissions */
    private function giveOnlyPermissions(array $permissions): void
    {
        setPermissionsTeamId(null);
        $role = Role::findOrCreate('limited-'.md5(implode(',', $permissions)), 'web');
        $role->syncPermissions($permissions);
        setPermissionsTeamId($this->club->id);
        $this->user->syncRoles([$role->name]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user = $this->user->fresh();
        $this->actingAs($this->user);
    }
}
