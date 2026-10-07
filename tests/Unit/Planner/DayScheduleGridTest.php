<?php

namespace Tests\Unit\Planner;

use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Support\DayScheduleGrid;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DayScheduleGridTest extends TestCase
{
    public function test_every_start_time_gets_its_own_row_from_row_two(): void
    {
        $grid = $this->grid([
            ['09:00', 1, null],
            ['10:45', 1, null],
            ['09:00', 2, null],
        ]);

        $this->assertSame(['09:00', '10:45'], $grid->times->all());
        $this->assertSame([2, 3, 2], $grid->cards()->pluck('row')->all());
        $this->assertSame([1, 1, 2], $grid->cards()->pluck('column')->all());
    }

    public function test_a_small_offset_stays_on_the_same_row(): void
    {
        $grid = $this->grid([
            ['10:45', 1, null],
            ['11:00', 2, null],
            ['12:30', 1, null],
            ['12:45', 3, null],
        ]);

        $this->assertSame(['10:45', '12:30'], $grid->times->all());
        $this->assertSame([2, 2, 3, 3], $grid->cards()->pluck('row')->all());
    }

    public function test_an_occupied_court_opens_a_new_row_even_within_the_margin(): void
    {
        $grid = $this->grid([
            ['10:45', 1, null],
            ['11:00', 1, null],
        ]);

        $this->assertSame([2, 3], $grid->cards()->pluck('row')->all());
    }

    public function test_the_margin_counts_from_the_first_time_of_the_row(): void
    {
        $grid = $this->grid([
            ['10:00', 1, null],
            ['10:15', 2, null],
            ['10:30', 3, null],
        ]);

        $this->assertSame([2, 2, 3], $grid->cards()->pluck('row')->all());
    }

    public function test_a_match_on_two_courts_becomes_one_card_over_both_columns(): void
    {
        $grid = $this->grid([['17:00', 1, 2]]);

        $this->assertCount(1, $grid->cards());
        $this->assertSame(1, $grid->cards()->first()['column']);
        $this->assertSame(2, $grid->cards()->first()['column_span']);
    }

    public function test_a_card_over_two_courts_starts_in_the_left_column(): void
    {
        // The second court can be the left one; the card must still start on
        // the left, otherwise the span runs outside the grid.
        $grid = $this->grid([['17:00', 3, 2]]);

        $this->assertSame(2, $grid->cards()->first()['column']);
        $this->assertSame(2, $grid->cards()->first()['column_span']);
    }

    public function test_adjacent_empty_places_become_one_block(): void
    {
        $grid = $this->grid([
            ['09:00', 1, null],
            ['19:30', 3, null],
        ]);

        // Row 2: courts 2 and 3 empty side by side. Row 3: courts 1 and 2 empty.
        $this->assertSame([
            ['column' => 2, 'column_span' => 2, 'row' => 2],
            ['column' => 1, 'column_span' => 2, 'row' => 3],
        ], $grid->emptyBlocks()->all());
    }

    public function test_a_hall_duty_spans_the_start_times_within_its_period(): void
    {
        $grid = $this->grid(
            [['09:00', 1, null], ['10:45', 1, null], ['12:30', 1, null]],
            [['08:15', '12:30'], ['12:30', '14:30']],
        );

        $blocks = $grid->hallDuties();

        $this->assertSame([2, 4], $blocks->pluck('row')->all());
        $this->assertSame([2, 1], $blocks->pluck('row_span')->all());
    }

    public function test_a_hall_duty_running_past_the_last_match_reaches_the_bottom(): void
    {
        $grid = $this->grid(
            [['09:00', 1, null], ['10:45', 1, null]],
            [['09:00', '22:00']],
        );

        $this->assertSame(1, $grid->hallDuties()->count());
        $this->assertSame(2, $grid->hallDuties()->first()['row_span']);
    }

    public function test_a_hall_duty_ending_before_the_first_match_is_dropped(): void
    {
        $grid = $this->grid([['12:30', 1, null]], [['08:00', '09:00']]);

        $this->assertTrue($grid->hallDuties()->isEmpty());
    }

    public function test_the_hall_duty_is_always_in_the_column_after_the_courts(): void
    {
        $this->assertSame(4, $this->grid([['09:00', 1, null]])->hallDutyColumn());
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int|null}>  $matches
     * @param  array<int, array{0: string, 1: string}>  $duties
     */
    private function grid(array $matches, array $duties = []): DayScheduleGrid
    {
        return new DayScheduleGrid($this->courts(), $this->matchList($matches), $this->duties($duties));
    }

    /** @return Collection<int, Court> */
    private function courts(): Collection
    {
        return collect([1, 2, 3])->map(function (int $number): Court {
            $court = new Court;
            $court->id = $number;
            $court->name = (string) $number;

            return $court;
        });
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2: int|null}>  $rows
     * @return Collection<int, FixtureMatch>
     */
    private function matchList(array $rows): Collection
    {
        return collect($rows)->map(function (array $row, int $index): FixtureMatch {
            [$time, $court, $secondCourt] = $row;
            $match = new FixtureMatch;
            $match->id = $index + 1;
            $match->time = $time;
            $match->court_id = $court;
            $match->second_court_id = $secondCourt;

            return $match;
        });
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $rows
     * @return Collection<int, HallDuty>
     */
    private function duties(array $rows): Collection
    {
        return collect($rows)->map(function (array $row): HallDuty {
            $duty = new HallDuty;
            $duty->start_time = $row[0];
            $duty->end_time = $row[1];

            return $duty;
        });
    }
}
