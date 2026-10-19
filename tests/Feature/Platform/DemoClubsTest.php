<?php

namespace Tests\Feature\Platform;

use App\Enums\ContactTopic;
use App\Filament\Platform\Resources\Clubs\Pages\ListClubs;
use App\Filament\Platform\Resources\ContactRequests\Pages\ListContactRequests;
use App\Models\Club;
use App\Models\ContactRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\ClubInvitation;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Support\ClubRole;
use App\Support\Demo\DemoClubBuilder;
use App\Support\Demo\DemoClubs;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class DemoClubsTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->artisan('planner:sync-roles')->assertSuccessful();
        $this->admin = User::factory()->platformAdmin()->create();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('platform');
    }

    private function demoRequest(ContactTopic $topic = ContactTopic::Demo, string $club = 'VV De Smash'): ContactRequest
    {
        return ContactRequest::query()->create([
            'topic' => $topic,
            'name' => 'Ilse Bakker',
            'club' => $club,
            'email' => 'Ilse@Example.test',
        ]);
    }

    /** @template T @param callable(): T $callback @return T */
    private function inClub(Club $club, callable $callback): mixed
    {
        return app(CurrentClub::class)->run($club, $callback);
    }

    public function test_the_demo_action_creates_a_filled_demo_club_and_invites_the_requester(): void
    {
        $request = $this->demoRequest();

        Livewire::test(ListContactRequests::class)
            ->callAction(TestAction::make('createDemo')->table($request))
            ->assertNotified();

        $request->refresh();
        $club = $request->demoClub;
        $this->assertInstanceOf(Club::class, $club);
        $this->assertSame('VV De Smash (demo)', $club->name);
        $this->assertMatchesRegularExpression('/^demo-vv-de-smash-[a-z0-9]{4}$/', $club->slug);
        $this->assertTrue($club->is_demo);
        $this->assertSame('groot', $club->plan?->slug);
        $this->assertTrue($club->trial_ends_at?->isSameDay(today()->addDays(14)));
        $this->assertFalse($club->isReadOnly());
        $this->assertNotNull($request->handled_at);

        $this->inClub($club, function (): void {
            $this->assertSame(24, Team::query()->count());
            $this->assertTrue(ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->where('is_published', true)->exists());
            $this->assertTrue(ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->exists());
            $this->assertSame(2, ChangeRequest::query()->where('status', ChangeRequestStatus::Open)->count());
            $this->assertSame([DemoClubBuilder::TEAM_PREFIX], PlannerSetting::current()->team_name_patterns);
        });

        $invitation = Invitation::query()->where('club_id', $club->id)->sole();
        $this->assertSame('ilse@example.test', $invitation->email);
        $this->assertSame(ClubRole::ClubAdmin, $invitation->role);
        $this->assertSame($this->admin->id, $invitation->invited_by);
        Notification::assertSentTo(
            new AnonymousNotifiable,
            ClubInvitation::class,
            fn (ClubInvitation $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ilse@example.test' && $notification->club->is($club),
        );
    }

    public function test_the_requester_name_is_used_without_an_organisation(): void
    {
        $club = app(DemoClubs::class)->createFor($this->demoRequest(club: ' '));

        $this->assertSame('Ilse Bakker (demo)', $club->name);
        $this->assertStringStartsWith('demo-ilse-bakker-', $club->slug);
    }

    public function test_a_request_gets_one_demo_club(): void
    {
        $request = $this->demoRequest();

        $first = app(DemoClubs::class)->createFor($request);
        $second = app(DemoClubs::class)->createFor(ContactRequest::query()->findOrFail($request->id));

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Club::query()->where('is_demo', true)->count());
        $this->assertSame(1, Invitation::query()->count());

        // Handled requests are filtered out by default.
        Livewire::test(ListContactRequests::class)
            ->removeTableFilters()
            ->assertActionHidden(TestAction::make('createDemo')->table($request))
            ->assertActionVisible(TestAction::make('openDemo')->table($request))
            ->assertActionHasUrl(TestAction::make('openDemo')->table($request), url('/app/'.$first->slug));
    }

    public function test_only_demo_requests_get_a_demo(): void
    {
        $question = $this->demoRequest(ContactTopic::Question);

        Livewire::test(ListContactRequests::class)
            ->assertActionHidden(TestAction::make('createDemo')->table($question))
            ->assertActionHidden(TestAction::make('openDemo')->table($question));

        $this->expectException(InvalidArgumentException::class);
        app(DemoClubs::class)->createFor($question);
    }

    public function test_demo_dates_are_relative_to_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-03-03 10:00'));
        $club = app(DemoClubs::class)->createFor($this->demoRequest());
        $saturday = CarbonImmutable::parse('2027-03-06');

        $this->inClub($club, function () use ($saturday): void {
            $published = ScheduleVersion::query()->where('is_published', true)->sole();
            $this->assertGreaterThan(10, FixtureMatch::query()->where('version_id', $published->id)->whereDate('date', $saturday)->count());
            $this->assertTrue(FixtureMatch::query()->where('nevobo_change_status', NevoboChangeStatus::Conflict)->exists());

            // The draft differs from the published version.
            $draft = ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->sole();
            $publishedTime = FixtureMatch::query()->where('version_id', $published->id)->where('match_code', 'D1L-DF')->value('time');
            $draftTime = FixtureMatch::query()->where('version_id', $draft->id)->where('match_code', 'D1L-DF')->value('time');
            $this->assertNotSame($publishedTime, $draftTime);
        });
    }

    public function test_demo_data_stays_in_the_demo_club(): void
    {
        $other = $this->createClub();
        $club = app(DemoClubs::class)->createFor($this->demoRequest());

        $this->inClub($other, function (): void {
            $this->assertSame(0, Team::query()->count());
            $this->assertSame(0, FixtureMatch::query()->count());
            $this->assertSame(0, ChangeRequest::query()->count());
        });
        $this->assertGreaterThan(0, FixtureMatch::withoutGlobalScopes()->where('club_id', $club->id)->count());
        $this->assertSame(FixtureMatch::withoutGlobalScopes()->count(), FixtureMatch::withoutGlobalScopes()->where('club_id', $club->id)->count());
    }

    public function test_a_demo_turns_read_only_after_the_trial(): void
    {
        $club = app(DemoClubs::class)->createFor($this->demoRequest());

        $this->travelTo(today()->addDays(15));

        $this->assertTrue($club->refresh()->isReadOnly());
    }

    public function test_the_prune_command_deletes_only_long_expired_demos(): void
    {
        $member = User::factory()->create();
        $expired = app(DemoClubs::class)->createFor($this->demoRequest(club: 'Verlopen'));
        $expired->update(['trial_ends_at' => today()->subDays(31)]);
        $member->clubs()->attach($expired);
        $member->assignClubRole($expired, ClubRole::ClubAdmin);

        $recent = Club::factory()->create(['is_demo' => true, 'trial_ends_at' => today()->subDays(29)]);
        $running = Club::factory()->create(['is_demo' => true, 'trial_ends_at' => today()->addDays(3)]);
        $converted = Club::factory()->create(['is_demo' => false, 'trial_ends_at' => today()->subDays(60)]);
        $normal = Club::factory()->create();

        $this->artisan('platform:prune-demo-clubs')->assertSuccessful();

        $this->assertModelMissing($expired);
        foreach ([$recent, $running, $converted, $normal] as $kept) {
            $this->assertModelExists($kept);
        }
        $this->assertDatabaseMissing('planner_matches', ['club_id' => $expired->id]);
        $this->assertDatabaseMissing('club_user', ['club_id' => $expired->id]);
        $this->assertDatabaseMissing('model_has_roles', ['club_id' => $expired->id]);
        $this->assertModelExists($member);
        $this->assertNull(ContactRequest::query()->sole()->demo_club_id);
    }

    public function test_converting_a_demo_clears_the_flag(): void
    {
        $club = app(DemoClubs::class)->createFor($this->demoRequest());
        $normal = Club::factory()->create();

        Livewire::test(ListClubs::class)
            ->assertActionHidden(TestAction::make('convertDemo')->table($normal))
            ->callAction(TestAction::make('convertDemo')->table($club))
            ->assertNotified();

        $this->assertFalse($club->refresh()->is_demo);
    }
}
