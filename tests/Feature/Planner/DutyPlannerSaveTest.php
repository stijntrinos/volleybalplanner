<?php

namespace Tests\Feature\Planner;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\Venue;
use App\Planner\Services\Duties\DutyPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\InteractsWithClubs;
use Tests\Feature\Planner\Concerns\BuildsDutyFixtures;
use Tests\TestCase;

/**
 * The *_auto flag decides what a next "redistribute" run may overwrite. When
 * it is wrong, exactly the manual corrections disappear.
 */
class DutyPlannerSaveTest extends TestCase
{
    use BuildsDutyFixtures, InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDutyClub();
    }

    public function test_an_accepted_suggestion_counts_as_automatically_assigned(): void
    {
        $match = $this->homeMatch(['referee' => null, 'referee_auto' => false]);

        $this->saveRow($match, suggestion: 'HS 2', chosen: 'HS 2');

        $match->refresh();
        $this->assertSame('HS 2', $match->referee);
        $this->assertTrue($match->referee_auto);
    }

    public function test_a_manual_correction_stays_protected_from_redistribute(): void
    {
        $match = $this->homeMatch(['referee' => null, 'referee_auto' => false]);

        // The generator suggested HS 2, the committee made it DS 1.
        $this->saveRow($match, suggestion: 'HS 2', chosen: 'DS 1');

        $match->refresh();
        $this->assertSame('DS 1', $match->referee);
        $this->assertFalse($match->referee_auto);
    }

    public function test_an_unchanged_value_keeps_its_original_origin(): void
    {
        $manual = $this->homeMatch(['referee' => 'DS 1', 'referee_auto' => false]);
        $automatic = $this->homeMatch(['referee' => 'HS 3', 'referee_auto' => true]);

        // In fill mode the generator leaves a filled value and suggests exactly that.
        $this->saveRow($manual, suggestion: 'DS 1', chosen: 'DS 1');
        $this->saveRow($automatic, suggestion: 'HS 3', chosen: 'HS 3');

        $this->assertFalse($manual->refresh()->referee_auto);
        $this->assertTrue($automatic->refresh()->referee_auto);
    }

    public function test_a_cleared_duty_counts_as_manual(): void
    {
        $match = $this->homeMatch(['scorer' => 'HS 1', 'scorer_auto' => true]);

        $this->saveRow($match, suggestion: 'HS 1', chosen: 'HS 1', scorerSuggestion: 'HS 1', scorerChosen: '');

        $match->refresh();
        $this->assertNull($match->scorer);
        $this->assertFalse($match->scorer_auto);
    }

    public function test_applying_one_block_leaves_the_other_venue_alone(): void
    {
        $this->teams();
        $otherVenue = Venue::factory()->create();
        $this->homeMatch(['match_code' => 'W-A']);
        $this->homeMatch(['match_code' => 'W-B', 'venue_id' => $otherVenue->id]);
        $draft = $this->planner()->generate($this->version);

        $this->planner()->save($this->version, $this->block($draft, $this->venue->id), null, $this->saturday, $this->saturday, $this->venue->id, true);

        $duties = HallDuty::query()->get();
        $this->assertCount(1, $duties);
        $this->assertSame($this->venue->id, $duties->first()->venue_id);
        $this->assertNotNull(FixtureMatch::query()->where('match_code', 'W-A')->value('scorer'));
        $this->assertNull(FixtureMatch::query()->where('match_code', 'W-B')->value('scorer'));
    }

    public function test_applying_a_block_without_venue_keeps_the_venue_hall_duty(): void
    {
        $this->teams();
        $this->homeMatch(['match_code' => 'W-A']);
        $this->homeMatch(['match_code' => 'W-NONE', 'venue_id' => null, 'time' => '12:00']);
        $draft = $this->planner()->generate($this->version);

        $this->planner()->save($this->version, $this->block($draft, $this->venue->id), null, $this->saturday, $this->saturday, $this->venue->id, true);
        $this->planner()->save($this->version, $this->block($draft, null), null, $this->saturday, $this->saturday, null, true);

        $duties = HallDuty::query()->get();
        $this->assertCount(1, $duties, 'the hall duty of the venue stays');
        $this->assertSame($this->venue->id, $duties->first()->venue_id);
    }

    public function test_a_locked_hall_duty_survives_saving_and_a_new_redistribution(): void
    {
        $this->teams();
        $this->homeMatch();
        $draft = $this->planner()->generate($this->version);
        $draft['hall_duties'][0]['team_code'] = 'JA 1';
        $draft['hall_duties'][0]['locked'] = true;

        $this->planner()->save($this->version, $draft);
        $this->assertFalse(HallDuty::query()->firstOrFail()->team_auto);

        // A fresh draft must take the lock from the database, or the planner
        // redistributes the manual choice anyway.
        $fresh = $this->planner()->generate($this->version, DutyPlanner::MODE_REDISTRIBUTE);

        $this->assertTrue($fresh['hall_duties'][0]['locked']);
        $this->assertSame('JA 1', $fresh['hall_duties'][0]['team_code']);
    }

    public function test_a_locked_assignment_stays_manual_when_saved(): void
    {
        $this->teams();
        $match = $this->homeMatch();
        $draft = $this->planner()->generate($this->version);
        $draft['matches'][0]['locked']['scorer'] = true;

        $this->planner()->save($this->version, $draft);

        $this->assertFalse($match->refresh()->scorer_auto);
        $this->assertTrue($match->referee_auto);
    }

    public function test_saving_is_logged(): void
    {
        $this->teams();
        $this->homeMatch();

        $this->planner()->save($this->version, $this->planner()->generate($this->version));

        $this->assertSame('planner-duties', Activity::query()->latest('id')->value('log_name'));
    }

    private function teams(): void
    {
        foreach (['HS 4', 'DS 2', 'JA 1'] as $code) {
            $this->team($code, ['name' => 'AETOS '.$code]);
        }
    }

    /** The part of a draft for one day and venue, as the duty planner page applies a block. */
    private function block(array $draft, ?int $venueId): array
    {
        $inBlock = fn (array $row): bool => $row['date'] === $this->saturday && $row['venue_id'] === $venueId;

        return [
            'matches' => array_values(array_filter($draft['matches'], $inBlock)),
            'hall_duties' => array_values(array_filter($draft['hall_duties'], $inBlock)),
        ];
    }

    private function saveRow(FixtureMatch $match, string $suggestion, string $chosen, ?string $scorerSuggestion = null, ?string $scorerChosen = null): void
    {
        $this->planner()->save($this->version, ['matches' => [[
            'id' => $match->id,
            'referee' => $chosen,
            'suggested_referee' => $suggestion,
            'scorer' => $scorerChosen ?? (string) $match->scorer,
            'suggested_scorer' => $scorerSuggestion ?? (string) $match->scorer,
        ]]]);
    }

    private function planner(): DutyPlanner
    {
        return app(DutyPlanner::class);
    }
}
