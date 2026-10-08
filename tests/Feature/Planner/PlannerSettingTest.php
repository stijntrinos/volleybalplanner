<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeReason;
use App\Planner\Models\PlannerSetting;
use App\Planner\Support\ClubTeamMatcher;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class PlannerSettingTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    public function test_current_creates_one_row_per_club_with_defaults(): void
    {
        $club = $this->useClub($this->createClub());

        $settings = PlannerSetting::current();

        $this->assertTrue($settings->is(PlannerSetting::current()));
        $this->assertSame($club->id, $settings->club_id);
        $this->assertSame([], $settings->team_name_patterns);
        $this->assertSame('#0B7F8A', $settings->refresh()->primary_color);
        $this->assertSame(15, $settings->buffer_minutes);
        $this->assertSame(1, PlannerSetting::withoutGlobalScope('club')->count());
    }

    public function test_reasons_fall_back_to_defaults_and_always_end_with_other(): void
    {
        $this->useClub($this->createClub());
        $settings = PlannerSetting::current();

        $this->assertSame([...PlannerSetting::defaultRequestReasons(), 'other'], $settings->requestReasons());

        $settings->update(['request_reasons' => [' Eigen reden ', '', 'Overig', 'other']]);
        $this->assertSame(['Eigen reden', ChangeReason::Other->value], $settings->requestReasons());
        $this->assertSame([...PlannerSetting::defaultDutyRequestReasons(), 'other'], $settings->dutyRequestReasons());
    }

    public function test_hall_duty_days_and_exceptions(): void
    {
        $this->useClub($this->createClub());
        $settings = PlannerSetting::current();

        $this->assertTrue($settings->hasHallDutyOnDate('2026-09-12'));
        $this->assertFalse($settings->hasHallDutyOnDate('2026-09-16'));

        $settings->update(['hall_duty_exceptions' => ['2026-09-12', 'junk', '2026-09-12']]);
        $this->assertSame(['2026-09-12'], $settings->hallDutyExceptions());
        $this->assertFalse($settings->hasHallDutyOnDate('2026-09-12'));
    }

    public function test_team_group_order_keeps_unknown_groups_out_and_missing_groups_last(): void
    {
        $this->useClub($this->createClub());
        $settings = PlannerSetting::current();
        $settings->update(['team_group_order' => ['women', 'bogus', 'men']]);

        $this->assertSame(['women', 'men'], array_slice($settings->teamGroupOrder(), 0, 2));
        $this->assertSame(TeamCode::CATEGORY_ORDER, array_values(array_intersect(TeamCode::CATEGORY_ORDER, $settings->teamGroupOrder())));
        $this->assertCount(count(TeamCode::CATEGORY_ORDER), $settings->teamGroupOrder());
    }

    public function test_the_matcher_reads_the_patterns_of_the_current_club(): void
    {
        $a = $this->createClub();
        $b = $this->createClub();
        $current = app(CurrentClub::class);
        $current->run($a, fn () => PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]));

        $this->assertTrue($current->run($a, fn (): bool => TeamCode::isOwnTeam('AETOS HS 1')));
        $this->assertFalse($current->run($b, fn (): bool => TeamCode::isOwnTeam('AETOS HS 1')));
        $this->assertTrue(ClubTeamMatcher::forClub($a)->isOwnTeamWithCode('AETOS HS 1', 'HS 1'));

        $current->run($b, fn () => PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]));
        $this->assertTrue($current->run($b, fn (): bool => TeamCode::isOwnTeamWithCode('AETOS HS 1', 'HS1')));
    }
}
