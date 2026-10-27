<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Filament\Pages\DutyPlanner as DutyPlannerPage;
use App\Planner\Filament\Pages\HomeMatches;
use App\Planner\Filament\Resources\Teams\Pages\EditTeam;
use App\Planner\Filament\Resources\Teams\RelationManagers\AbsencesRelationManager;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\PointsListImport;
use App\Planner\Services\Duties\RuleEngine;
use App\Planner\Services\ScheduleVersionService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * The remaining parts of the planning rules: refereeing at VolleyStars, a new
 * version keeping its points, the display duration, the points list of the
 * volunteer committee and team absence.
 */
class DutyPlanningAdditionsTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
        PlannerSetting::current()->update([
            'points_referee_duty' => 1, 'points_scorer_duty' => 1, 'points_hall_duty' => 2, 'points_volleystars' => 3,
        ]);
    }

    public function test_refereeing_at_volleystars_is_done_by_seniors_or_a_youth(): void
    {
        $jb1 = $this->team('JB 1');
        $ma1 = $this->team('MA 1');
        $hs4 = $this->team('HS 4');
        $ds10 = $this->team('DS 10', ['never_referees' => true]);
        $block = $this->block();

        $context = $this->context();
        $duty = $context->dutyForVolleystars($context->volleystarsBlocks()->firstWhere('id', $block->id), VolleystarsRole::Refereeing, 0);
        $soft = fn (Team $team): array => app(RuleEngine::class)->soft($context->teamWithCode($team->code), $duty, $context);

        $this->assertSame(['10:00', '11:30'], [$duty['start'], $duty['end']], 'refereeing has its own time slot');
        $this->assertContains('Het fluiten bij VolleyStars doen senioren of A-jeugd.', $soft($jb1));
        $this->assertSame([], $soft($ma1));
        $this->assertSame([], $soft($hs4));
        $this->assertContains('Fluit nooit.', $soft($ds10));
    }

    public function test_a_new_version_keeps_the_points_and_the_refereeing_slot(): void
    {
        $hs4 = $this->team('HS 4');
        HallDuty::factory()->create([
            'version_id' => $this->version->id, 'date' => $this->saturday, 'venue_id' => $this->venue->id,
            'start_time' => '09:00', 'end_time' => '12:00', 'team_id' => $hs4->id,
        ]);
        $block = $this->block();
        $block->tasks()->create(['role' => VolleystarsRole::Refereeing, 'team_id' => $hs4->id, 'sort_order' => 0]);
        PlannerSetting::current()->update(['points_hall_duty' => 9, 'points_volleystars' => 9]);

        $copy = app(ScheduleVersionService::class)->duplicate($this->version, 'Kopie');

        $this->assertSame(2, $copy->hallDuties()->value('points'));
        $newBlock = $copy->volleystarsBlocks()->with('tasks')->first();
        $this->assertSame(3, $newBlock->tasks->first()->points);
        $this->assertSame(VolleystarsRole::Refereeing, $newBlock->tasks->first()->role);
        $this->assertSame('10:00', substr((string) $newBlock->refereeing_start, 0, 5));
    }

    public function test_the_match_duration_is_for_display_only(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $this->team('HS 4');
        $this->homeMatch(['time' => '10:00', 'duration_minutes' => 90]);

        $component = Livewire::test(DutyPlannerPage::class)->set('versionId', $this->version->id)->call('generate');
        $this->assertSame('12:00', $component->instance()->displayEndTime('10:00'), 'default 120 minutes');

        PlannerSetting::current()->update(['display_match_duration' => 150]);
        Livewire::test(DutyPlannerPage::class)->set('versionId', $this->version->id)->call('generate')->assertSee('tot 12:30');

        // The rules work with the duration of the match itself (90 minutes).
        $context = $this->context();
        $this->assertSame('11:30', $context->dutyForMatch($context->homeMatches()->first(), PlanningContext::DUTY_SCORER)['end']);
    }

    public function test_the_points_list_of_the_volunteer_committee_is_read(): void
    {
        $ds1 = $this->team('DS 1');
        $hs2 = $this->team('HS 2', ['volunteer_points_percentage' => 50]);
        $ma3 = $this->team('MA 3');

        $outcome = app(PointsListImport::class)->import(implode("\n", [
            "Team\tBehoefte\tGeleverd\tNog in te vullen\tPercentage\tMinimumpercentage",
            "DS 1\t312\t137\t61\t72,0%\t70",
            "HS2\t1.200\t40\t\t\t",
            "MA 3\t100\t10\t\t\t101",
            "JX 9\t10\t0\t\t\t",
        ]));

        $this->assertSame(['DS 1', 'HS 2'], $outcome['updated']);
        $this->assertSame(['JX 9'], $outcome['unknown']);
        $this->assertSame(["MA 3: Minimumpercentage '101' ligt niet tussen 0 en 100."], $outcome['errors']);
        $this->assertSame([312, 137, 70], [$ds1->fresh()->volunteer_points_used, $ds1->fresh()->volunteer_points_earned, $ds1->fresh()->volunteer_points_percentage]);
        $this->assertSame(1200, $hs2->fresh()->volunteer_points_used);
        $this->assertNull($hs2->fresh()->volunteer_points_percentage, 'an empty minimum percentage means 100%');
        $this->assertSame(0, $ma3->fresh()->volunteer_points_used, 'a line with an error is left alone');
    }

    public function test_the_points_list_may_come_without_a_header_and_as_csv(): void
    {
        $ds1 = $this->team('DS 1');

        app(PointsListImport::class)->import('DS 1;200;50;90;25,0%;45');

        $this->assertSame([200, 50, 45], [$ds1->fresh()->volunteer_points_used, $ds1->fresh()->volunteer_points_earned, $ds1->fresh()->volunteer_points_percentage]);
    }

    public function test_the_points_list_only_touches_teams_of_the_current_club(): void
    {
        $this->team('DS 1', ['volunteer_points_used' => 7]);
        $this->useClub($this->createClub());

        $outcome = app(PointsListImport::class)->import('DS 1;200;50;90;25,0%;45');

        $this->assertSame(['DS 1'], $outcome['unknown']);
        $this->assertSame(7, Team::query()->withoutGlobalScopes()->where('code', 'DS 1')->value('volunteer_points_used'));
    }

    public function test_the_committee_manages_the_absence_of_a_team(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $team = $this->team('HS 4');

        Livewire::test(AbsencesRelationManager::class, ['ownerRecord' => $team, 'pageClass' => EditTeam::class])
            ->callTableAction('create', data: ['date_from' => $this->saturday, 'reason' => 'Toernooi'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Toernooi', $team->absences()->value('reason'));
        $this->assertSame($team->club_id, $team->absences()->value('club_id'));
    }

    public function test_the_volleystars_choice_follows_the_planning_rules_and_a_slot_keeps_its_points(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $jb1 = $this->team('JB 1', ['volunteer_points_used' => 50]);
        $hs4 = $this->team('HS 4', ['volunteer_points_used' => 50]);
        $block = $this->block();
        $block->tasks()->create(['role' => VolleystarsRole::Scoring, 'team_id' => $jb1->id, 'sort_order' => 0]);
        PlannerSetting::current()->update(['points_volleystars' => 5]);

        $component = Livewire::test(HomeMatches::class)
            ->set('versionId', $this->version->id)
            ->call('openVolleystars', $block->id);

        // Scoring at VolleyStars is done by a B youth team.
        $scoring = $component->get('volleystarsChoice')['scoring'];
        $this->assertArrayHasKey($jb1->id, $scoring['Voorgesteld']);
        $this->assertStringContainsString('Het tellen bij VolleyStars doet een B-jeugdteam.', $scoring['Niet voorgesteld'][$hs4->id]);

        $component
            ->set('modalVolleystarsTeams.scoring', [$jb1->id, $hs4->id, null])
            ->call('saveVolleystars')
            ->assertHasNoErrors();

        $this->assertSame(
            [3, 5],
            $block->tasks()->where('role', VolleystarsRole::Scoring)->orderBy('sort_order')->pluck('points')->all(),
            'the existing slot keeps 3 points, the new one gets the default of now',
        );
    }

    private function block(): VolleystarsBlock
    {
        return VolleystarsBlock::factory()->create([
            'version_id' => $this->version->id, 'date' => $this->saturday, 'venue_id' => $this->venue->id,
            'setup_start' => '08:30', 'setup_end' => '09:00', 'teardown_start' => '12:00', 'teardown_end' => '12:30',
            'scoring_start' => '09:00', 'scoring_end' => '12:00', 'refereeing_start' => '10:00', 'refereeing_end' => '11:30',
        ]);
    }
}
