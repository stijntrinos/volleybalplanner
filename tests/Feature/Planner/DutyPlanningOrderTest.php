<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\VolleystarsRole;
use App\Planner\Filament\Pages\DutyPlanner as DutyPlannerPage;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamAbsence;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Planner\Services\Duties\DutyPlanner;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\TeamChoice;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * Step 3 of the planning rules: who goes first, plus the soft rules that
 * keep a team out of the proposal and the team choice on the screen.
 */
class DutyPlanningOrderTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    private const REFEREE = PlanningContext::DUTY_REFEREE;

    private const SCORER = PlanningContext::DUTY_SCORER;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
        PlannerSetting::current()->update([
            'points_referee_duty' => 1, 'points_scorer_duty' => 1, 'points_hall_duty' => 2, 'points_volleystars' => 3,
        ]);
    }

    public function test_a_team_below_the_minimum_goes_first(): void
    {
        $this->standing('HS 4', need: 100, earned: 120);
        $this->standing('HS 5', need: 100, earned: 80);

        $this->assertSame('HS 5', $this->best($this->match('10:00'), self::SCORER));
    }

    public function test_below_the_minimum_goes_before_never_referees(): void
    {
        $this->standing('DS 8', need: 100, earned: 120, extra: ['never_referees' => true]);
        $this->standing('HS 5', need: 100, earned: 80);

        $this->assertSame('HS 5', $this->best($this->match('10:00'), self::SCORER), 'V3 before V4');
    }

    public function test_never_referees_goes_first_on_an_equal_standing(): void
    {
        $this->standing('DS 8', need: 100, earned: 80, extra: ['never_referees' => true]);
        $this->standing('DS 2', need: 100, earned: 80);

        $this->assertSame('DS 8', $this->best($this->match('10:00'), self::SCORER));
    }

    public function test_a_team_finishes_its_volleystars_block(): void
    {
        $above = $this->standing('HS 4', need: 100, earned: 120);
        $this->standing('HS 5', need: 100, earned: 80);
        $block = VolleystarsBlock::factory()->create([
            'version_id' => $this->version->id, 'date' => $this->saturday, 'venue_id' => $this->venue->id,
            'setup_start' => '08:30', 'setup_end' => '09:00', 'teardown_start' => '12:00', 'teardown_end' => '12:30',
            'scoring_start' => '09:00', 'scoring_end' => '12:00',
        ]);
        VolleystarsTask::factory()->create(['block_id' => $block->id, 'role' => VolleystarsRole::Teardown, 'team_id' => $above->id]);

        $context = $this->context();
        $duty = $context->dutyForVolleystars($context->volleystarsBlocks()->first(), VolleystarsRole::Setup, 0);

        $this->assertSame('HS 4', app(TeamChoice::class)->best($duty, $context)?->code, 'V1 before V3');
    }

    public function test_refereeing_b_youth_is_an_exception_for_a_youth(): void
    {
        $this->standing('JA 1', need: 100, earned: 50);
        $this->standing('HS 4', need: 100, earned: 120);
        $target = $this->match('10:00', 'AETOS JB 1');

        $this->assertSame('HS 4', $this->best($target, self::REFEREE), 'V2 before V3');
        $this->assertContains('Fluit bij voorkeur alleen C-jeugd; dit is een uitzondering.', $this->candidate($target, self::REFEREE, 'JA 1')['reasons']);
    }

    public function test_who_is_furthest_behind_goes_first(): void
    {
        $this->standing('HS 4', need: 100, earned: 40);
        $this->standing('HS 5', need: 200, earned: 50);

        $this->assertSame('HS 5', $this->best($this->match('10:00'), self::SCORER), '25% before 40%');
    }

    public function test_a_team_at_its_weekly_maximum_is_not_proposed_but_may_be_chosen(): void
    {
        PlannerSetting::current()->update(['max_duties_per_week' => 1]);
        $this->standing('HS 4', need: 100, earned: 0);
        $this->standing('HS 5', need: 100, earned: 90);
        $this->match('10:00', extra: ['scorer' => 'HS 4']);
        $target = $this->match('14:00');

        $hs4 = $this->candidate($target, self::REFEREE, 'HS 4');
        $this->assertSame(TeamChoice::NOT_PROPOSED, $hs4['status']);
        $this->assertContains('Niet voorgesteld: Het weekmaximum van 1 diensten is bereikt.', $hs4['reasons']);
        $this->assertSame('HS 5', $this->best($target, self::REFEREE));

        $validation = app(DutyPlanner::class)->validate($this->version, ['matches' => [
            ['id' => $target->id, 'referee' => 'HS 4', 'scorer' => ''],
        ], 'hall_duties' => []]);
        $this->assertSame(0, $validation['count'], 'the committee can still choose the team');
    }

    public function test_the_hall_duty_maximum_per_day_is_soft(): void
    {
        PlannerSetting::current()->update(['hall_duty_weekdays' => [6]]);
        $ds4 = $this->standing('DS 4', need: 100, earned: 0);
        $ds5 = $this->standing('DS 5', need: 100, earned: 90);
        foreach (['10:00', '12:00', '14:00'] as $time) {
            $this->match($time);
        }
        $rounds = $this->context()->rounds->all();
        foreach ([0, 1] as $round) {
            HallDuty::factory()->create([
                'version_id' => $this->version->id, 'date' => $this->saturday, 'venue_id' => $this->venue->id,
                'start_time' => $rounds[$round]['slot']['start'], 'end_time' => $rounds[$round]['slot']['end'], 'team_id' => $ds4->id,
            ]);
        }
        $slot = $rounds[2]['slot'];
        $best = function () use ($slot): ?string {
            $context = $this->context();

            return app(TeamChoice::class)->best($context->dutyForHallDuty($slot['date'], $slot['venue_id'], $slot['start'], $slot['end']), $context)?->code;
        };

        $this->assertSame('DS 5', $best(), 'V0 before V3');

        $ds5->update(['include_in_duties' => false]);
        $this->assertSame('DS 4', $best(), 'without another team it may anyway');
    }

    public function test_a_reported_absence_keeps_the_team_out_of_the_proposal(): void
    {
        $hs4 = $this->standing('HS 4', need: 100, earned: 0);
        $this->standing('HS 5', need: 100, earned: 90);
        TeamAbsence::factory()->create(['team_id' => $hs4->id, 'date_from' => $this->saturday, 'reason' => 'toernooi']);
        $target = $this->match('10:00');

        $this->assertSame('HS 5', $this->best($target, self::SCORER));
        $this->assertContains('Niet voorgesteld: Afgemeld: toernooi', $this->candidate($target, self::SCORER, 'HS 4')['reasons']);
    }

    public function test_an_absence_with_times_only_covers_those_hours(): void
    {
        $hs4 = $this->standing('HS 4', need: 100, earned: 0);
        TeamAbsence::factory()->create(['team_id' => $hs4->id, 'date_from' => $this->saturday, 'start_time' => '14:00', 'end_time' => '18:00', 'reason' => null]);

        $this->assertSame('HS 4', $this->best($this->match('10:00'), self::SCORER));
        $this->assertContains('Niet voorgesteld: Afgemeld voor deze dag', $this->candidate($this->match('15:00'), self::SCORER, 'HS 4')['reasons']);
    }

    public function test_a_team_works_in_one_venue_per_day(): void
    {
        $otherVenue = Venue::factory()->create();
        $this->standing('HS 4', need: 100, earned: 0);
        $this->match('10:00', extra: ['venue_id' => $otherVenue->id, 'scorer' => 'HS 4']);
        $later = $this->match('14:00');
        $atTheSameTime = $this->match('10:30');

        $this->assertContains('Niet voorgesteld: Het team heeft die dag al een dienst in een andere sporthal.', $this->candidate($later, self::REFEREE, 'HS 4')['reasons']);
        $this->assertContains('Het team heeft op dat moment al een dienst in een andere sporthal.', $this->candidate($atTheSameTime, self::REFEREE, 'HS 4')['reasons'], 'H4');
    }

    public function test_a_duty_shortly_after_another_moves_back(): void
    {
        PlannerSetting::current()->update(['min_days_between_duties' => 7]);
        $this->standing('HS 4', need: 100, earned: 50);
        $this->standing('HS 5', need: 100, earned: 50);
        $earlier = CarbonImmutable::parse($this->saturday)->subDays(3);
        $this->match('10:00', extra: ['date' => $earlier->toDateString(), 'scorer' => 'HS 4']);
        $target = $this->match('10:00');

        $this->assertSame('HS 5', $this->best($target, self::SCORER));
        $this->assertContains('Heeft al een dienst op '.$earlier->format('d-m').' (binnen 7 dagen).', $this->candidate($target, self::SCORER, 'HS 4')['reasons']);
    }

    /** The day maximum is soft: a team on it moves behind the teams still under it. */
    public function test_a_team_on_the_day_maximum_moves_back(): void
    {
        PlannerSetting::current()->update(['max_duties_per_day' => 2]);
        $this->standing('DS 5', need: 100, earned: 0);
        $this->standing('DS 6', need: 100, earned: 90);
        $this->standing('DS 7', need: 100, earned: 95);
        foreach (['09:00', '11:00', '13:00'] as $time) {
            $this->match($time);
        }

        $draft = app(DutyPlanner::class)->generate($this->version);

        $duties = collect($draft['matches'])->flatMap(fn (array $row): array => [$row['referee'], $row['scorer']])->countBy();
        $this->assertSame(['DS 5' => 2, 'DS 6' => 2, 'DS 7' => 2], $duties->sortKeys()->all());
    }

    public function test_the_team_choice_on_the_screen_shows_the_order_and_puts_the_choice_in_the_draft(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        $this->standing('HS 4', need: 100, earned: 120);
        $this->standing('HS 5', need: 100, earned: 80);
        $this->match('10:00');

        $component = Livewire::test(DutyPlannerPage::class)
            ->set('versionId', $this->version->id)
            ->call('generate')
            ->call('openTeamChoice', 'match', 0, 'scorer')
            ->assertDispatched('open-modal', id: 'duty-team-choice')
            ->assertSee('Teldienst')
            ->assertSee('% van minimum');

        $this->assertSame(['HS 5', 'HS 4'], array_slice(array_column($component->get('teamChoice')['candidates'], 'code'), 0, 2));

        $component->call('chooseTeam', 1);
        $this->assertSame('HS 4', $component->get('draft.matches.0.scorer'));
        $this->assertNull($component->get('teamChoice'));
    }

    public function test_the_team_choice_shows_the_balance_before_and_after_moving(): void
    {
        $this->actingAsClubUser(club: app(CurrentClub::class)->require());
        PlannerSetting::current()->update(['hall_duty_weekdays' => []]);
        $this->standing('HS 4', need: 100, earned: 50);
        $this->match('10:00', extra: ['referee' => 'Nevobo', 'scorer' => 'HS 4', 'scorer_points' => 10]);

        $component = Livewire::test(DutyPlannerPage::class)
            ->set('versionId', $this->version->id)
            ->call('generate')
            ->call('openTeamChoice', 'match', 0, 'scorer');

        $this->assertSame(
            ['code' => 'HS 4', 'before' => 60.0, 'after' => 50.0, 'before_remaining' => 40, 'after_remaining' => 50],
            $component->get('teamChoice')['balance'],
        );
    }

    private function standing(string $code, int $need, int $earned, array $extra = []): Team
    {
        return $this->team($code, $extra + ['volunteer_points_used' => $need, 'volunteer_points_earned' => $earned]);
    }

    private function match(string $time, string $home = 'AETOS HS 9', array $extra = []): FixtureMatch
    {
        return $this->homeMatch($extra + ['time' => $time, 'duration_minutes' => 105, 'home_team_name' => $home]);
    }
}
