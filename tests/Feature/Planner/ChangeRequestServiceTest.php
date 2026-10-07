<?php

namespace Tests\Feature\Planner;

use App\Models\Club;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\DutyType;
use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Venue;
use App\Planner\Services\ChangeRequestService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ChangeRequestServiceTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    private Venue $venue;

    private Team $ds1;

    private ScheduleVersion $published;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-09-20 10:00:00');
        $this->useClub($this->createClub());
        PlannerSetting::current()->update(['team_name_patterns' => ['AETOS']]);
        $this->venue = Venue::factory()->create(['name' => 'Middachtensingel']);
        $this->ds1 = Team::factory()->create(['code' => 'DS 1', 'name' => 'AETOS DS 1']);
        $this->published = ScheduleVersion::factory()->create([
            'status' => ScheduleVersionStatus::Published, 'is_published' => true,
        ]);
    }

    public function test_the_form_is_open_by_default(): void
    {
        $this->assertTrue($this->service()->isOpen());
    }

    public function test_the_committee_can_close_the_form(): void
    {
        PlannerSetting::current()->update(['requests_open' => false]);

        $this->assertFalse($this->service()->isOpen());
    }

    public function test_only_teams_with_an_upcoming_match_are_in_the_list(): void
    {
        $withoutMatch = Team::factory()->create(['code' => 'HS 9', 'name' => 'AETOS HS 9']);
        $this->match('W1', '2026-10-03', $this->ds1);

        $teams = $this->service()->teams();

        $this->assertSame([$this->ds1->id], $teams->pluck('id')->all());
        $this->assertNotContains($withoutMatch->id, $teams->pluck('id')->all());
    }

    public function test_the_match_list_shows_home_and_away_from_today(): void
    {
        $this->match('PAST', '2026-09-05', $this->ds1);
        $this->match('HOME', '2026-10-03', $this->ds1);
        $this->match('AWAY', '2026-10-10', $this->ds1, MatchType::Away);

        $codes = $this->service()->matches($this->ds1->id)->pluck('match_code')->all();

        $this->assertSame(['HOME', 'AWAY'], $codes, 'Matches already played should no longer be listed.');
    }

    public function test_the_matches_come_from_the_published_planning(): void
    {
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match('PUBLISHED', '2026-10-03', $this->ds1);
        $this->match('DRAFT', '2026-10-04', $this->ds1, MatchType::Home, $draft);

        $codes = $this->service()->matches($this->ds1->id)->pluck('match_code')->all();

        $this->assertSame(['PUBLISHED'], $codes);
    }

    public function test_without_a_published_planning_it_falls_back_on_the_newest_draft(): void
    {
        $this->published->update(['status' => ScheduleVersionStatus::Archived, 'is_published' => null]);
        $draft = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $this->match('DRAFT', '2026-10-04', $this->ds1, MatchType::Home, $draft);

        $codes = $this->service()->matches($this->ds1->id)->pluck('match_code')->all();

        $this->assertSame(['DRAFT'], $codes);
    }

    public function test_a_request_stores_the_match_code_next_to_the_id(): void
    {
        $match = $this->match('ABC1', '2026-10-03', $this->ds1);

        $request = $this->service()->submit([
            'team_id' => $this->ds1->id, 'match_id' => $match->id,
            'name' => 'Jan Jansen', 'email' => 'jan@example.com',
            'reason' => 'Vakantie of schoolactiviteit', 'explanation' => 'Halve selectie is weg.',
        ]);

        $this->assertSame('ABC1', $request->match_code);
        $this->assertSame($match->id, $request->match_id);
        $this->assertSame(ChangeRequestStatus::Open, $request->status);
        $this->assertSame('Jan Jansen', $request->name, 'Stored encrypted, readable back.');
    }

    public function test_accepting_and_rejecting_record_the_outcome(): void
    {
        $match = $this->match('ABC1', '2026-10-03', $this->ds1);
        $service = $this->service();
        $request = $service->submit([
            'team_id' => $this->ds1->id, 'match_id' => $match->id,
            'name' => 'Jan', 'email' => 'jan@example.com',
            'reason' => 'Vakantie of schoolactiviteit', 'explanation' => 'x',
        ]);

        $service->handle($request, ChangeRequestStatus::Accepted, 'Verzet naar 10 oktober.', null);

        $request->refresh();
        $this->assertSame(ChangeRequestStatus::Accepted, $request->status);
        $this->assertSame('Verzet naar 10 oktober.', $request->note);
        $this->assertNotNull($request->handled_at);
        $this->assertFalse($request->isOpen());
    }

    public function test_the_planner_recognises_an_open_request_also_after_duplicating_a_version(): void
    {
        $match = $this->match('ABC1', '2026-10-03', $this->ds1);
        $service = $this->service();
        $service->submit([
            'team_id' => $this->ds1->id, 'match_id' => $match->id,
            'name' => 'Jan', 'email' => 'jan@example.com',
            'reason' => 'Vakantie of schoolactiviteit', 'explanation' => 'x',
        ]);

        // A new draft gets copied match rows with new ids; only the match code
        // survives that.
        $newVersion = ScheduleVersion::factory()->create(['status' => ScheduleVersionStatus::Draft]);
        $copy = $this->match('ABC1', '2026-10-03', $this->ds1, MatchType::Home, $newVersion);

        $codes = $service->openRequestCodes();

        $this->assertContains('ABC1', $codes);
        $this->assertNotSame($match->id, $copy->id);
    }

    public function test_without_the_change_requests_module_no_match_is_marked(): void
    {
        $match = $this->match('ABC1', '2026-10-03', $this->ds1);
        $this->service()->submit([
            'team_id' => $this->ds1->id, 'match_id' => $match->id,
            'name' => 'Jan', 'email' => 'jan@example.com', 'reason' => 'x', 'explanation' => 'x',
        ]);
        $this->club()->update(['features' => []]);

        $this->assertSame([], $this->service()->openRequestCodes());
    }

    /**
     * The club names its teams in a fixed order: men, women, then A, B and C
     * youth. Alphabetically "DS 10" landed between DS 1 and DS 2 and the youth
     * got mixed up.
     */
    public function test_the_teams_are_in_the_order_the_club_uses(): void
    {
        $codes = ['MC 1', 'DS 10', 'JB 2', 'HS 2', 'DS 2', 'Recreanten', 'JA 1', 'HS 10', 'MA 2', 'DS 1', 'HS 1', 'MB 1', 'JC 1'];
        foreach ($codes as $index => $code) {
            $team = Team::query()->firstOrCreate(['code' => $code], ['name' => 'AETOS '.$code]);
            $this->match('W'.$index, '2026-10-03', $team);
        }

        $result = $this->service()->teams()->pluck('code')->all();

        $this->assertSame([
            'HS 1', 'HS 2', 'HS 10',
            'DS 1', 'DS 2', 'DS 10',
            'JA 1', 'MA 2',
            'JB 2', 'MB 1',
            'JC 1', 'MC 1',
            'Recreanten',
        ], $result);
    }

    /**
     * A team called "AETOS Plauti DS 1" with the sponsor in it was read as one
     * word, so the team ended up behind the C youth and nobody found it.
     */
    public function test_a_team_name_with_a_sponsor_in_it_still_sits_with_the_women(): void
    {
        $plauti = Team::query()->firstOrCreate(['code' => 'Plauti DS 1'], ['name' => 'AETOS Plauti DS 1']);
        $hs1 = Team::factory()->create(['code' => 'HS 1', 'name' => 'AETOS HS 1']);
        $mc7 = Team::factory()->create(['code' => 'MC 7', 'name' => 'AETOS MC 7']);

        foreach ([$plauti, $hs1, $mc7] as $index => $team) {
            $this->match('P'.$index, '2026-10-03', $team);
        }

        $codes = $this->service()->teams()->pluck('code')->all();

        $this->assertSame(
            ['HS 1', 'DS 1', 'Plauti DS 1', 'MC 7'],
            $codes,
            'The women team belongs between the men and the C youth. DS 1 comes along because that code is in the name too.',
        );
    }

    public function test_a_group_without_a_category_stays_at_the_end(): void
    {
        $recreants = Team::factory()->create(['code' => 'Recreanten', 'name' => 'Recreanten']);
        $mixed = Team::factory()->create(['code' => 'Mixed 1', 'name' => 'Mixed 1']);
        $hs1 = Team::factory()->create(['code' => 'HS 1', 'name' => 'AETOS HS 1']);

        foreach ([$recreants, $mixed, $hs1] as $index => $team) {
            $this->match('G'.$index, '2026-10-03', $team);
        }

        $codes = $this->service()->teams()->pluck('code')->all();

        $this->assertSame(['HS 1', 'Mixed 1', 'Recreanten'], $codes, 'Mixed 1 does not belong with the women because of the D in Mixed.');
    }

    /**
     * Team names come as free text from imports and RSS, so not every match
     * has a linked team id. A team disappeared from the list while there were
     * matches for it.
     */
    public function test_a_match_without_a_linked_team_does_not_make_the_team_disappear(): void
    {
        FixtureMatch::factory()->create([
            'version_id' => $this->published->id, 'venue_id' => $this->venue->id, 'match_code' => 'LOOSE',
            'date' => '2026-10-03', 'time' => '19:30', 'duration_minutes' => 105, 'match_type' => MatchType::Home,
            'home_team_name' => 'AETOS DS 1', 'away_team_name' => 'Trivos DS 1',
            'home_team_id' => null, 'away_team_id' => null,
        ]);

        $service = $this->service();

        $this->assertSame(['DS 1'], $service->teams()->pluck('code')->all());
        $this->assertSame(['LOOSE'], $service->matches($this->ds1->id)->pluck('match_code')->all());
    }

    public function test_an_opponent_with_the_same_code_brings_in_no_team(): void
    {
        FixtureMatch::factory()->create([
            'version_id' => $this->published->id, 'venue_id' => $this->venue->id, 'match_code' => 'ELSEWHERE',
            'date' => '2026-10-03', 'time' => '19:30', 'duration_minutes' => 105, 'match_type' => MatchType::Home,
            'home_team_name' => 'Trivos DS 1', 'away_team_name' => 'Xanthos DS 1',
            'home_team_id' => null, 'away_team_id' => null,
        ]);

        $this->assertSame([], $this->service()->teams()->pluck('code')->all());
    }

    public function test_duties_list_the_referee_scorer_and_hall_duties_of_a_team(): void
    {
        $match = $this->match('ABC1', '2026-10-03', Team::factory()->create(['code' => 'HS 1', 'name' => 'AETOS HS 1']));
        $match->update(['referee' => 'DS 1', 'scorer' => 'DS1']);
        HallDuty::factory()->create([
            'version_id' => $this->published->id, 'team_id' => $this->ds1->id, 'venue_id' => $this->venue->id,
            'date' => '2026-10-04', 'start_time' => '09:00', 'end_time' => '13:00',
        ]);

        $service = $this->service();
        $duties = $service->duties($this->ds1->id);

        $this->assertSame([$this->ds1->id], $service->dutyTeams()->pluck('id')->all());
        $this->assertSame(
            [DutyType::Referee->value, DutyType::Scorer->value, DutyType::HallDuty->value],
            $duties->pluck('type')->all(),
        );
        $this->assertSame('09:00–13:00', $duties[2]['time']);

        $request = $service->submitDuty([
            'team_id' => $this->ds1->id, 'duty_key' => $duties[2]['key'],
            'name' => 'Jan', 'email' => 'jan@example.com', 'phone' => '0612345678', 'contact_preference' => 'whatsapp',
            'reason' => 'x', 'explanation' => 'x',
        ]);
        $this->assertSame(DutyType::HallDuty, $request->duty_type);
        $this->assertSame('Middachtensingel', $request->duty_location);
    }

    public function test_an_unknown_duty_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->submitDuty([
            'team_id' => $this->ds1->id, 'duty_key' => 'referee:999',
            'name' => 'Jan', 'email' => 'jan@example.com', 'reason' => 'x', 'explanation' => 'x',
        ]);
    }

    public function test_without_the_duties_module_there_are_no_duties(): void
    {
        $this->match('ABC1', '2026-10-03', $this->ds1)->update(['referee' => 'DS 1']);
        $this->club()->update(['features' => ['change_requests']]);

        $this->assertCount(0, $this->service()->dutyTeams());
        $this->assertCount(0, $this->service()->duties($this->ds1->id));
    }

    private function service(): ChangeRequestService
    {
        return app(ChangeRequestService::class);
    }

    private function club(): Club
    {
        return app(CurrentClub::class)->require();
    }

    private function match(string $code, string $date, Team $team, MatchType $type = MatchType::Home, ?ScheduleVersion $version = null): FixtureMatch
    {
        return FixtureMatch::factory()->create([
            'version_id' => ($version ?? $this->published)->id,
            'venue_id' => $this->venue->id, 'match_code' => $code,
            'date' => $date, 'time' => '19:30', 'duration_minutes' => 105, 'match_type' => $type,
            'home_team_name' => $type === MatchType::Home ? $team->name : 'Tegenstander',
            'away_team_name' => $type === MatchType::Home ? 'Tegenstander' : $team->name,
            'home_team_id' => $type === MatchType::Home ? $team->id : null,
            'away_team_id' => $type === MatchType::Away ? $team->id : null,
        ]);
    }
}
