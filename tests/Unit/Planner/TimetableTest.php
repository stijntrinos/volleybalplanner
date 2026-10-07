<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\Timetable;
use PHPUnit\Framework\TestCase;

class TimetableTest extends TestCase
{
    public function test_matches_without_overlap_get_the_full_width(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 1, 'start' => 600, 'end' => 705],
            ['id' => 2, 'start' => 705, 'end' => 810],
        ]);

        $this->assertSame(['column' => 0, 'columns' => 1], $layout[1]);
        $this->assertSame(['column' => 0, 'columns' => 1], $layout[2]);
    }

    public function test_two_overlapping_matches_each_get_half(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 'mb4', 'start' => 600, 'end' => 705],
            ['id' => 'ma2', 'start' => 600, 'end' => 705],
        ]);

        $this->assertSame(['column' => 0, 'columns' => 2], $layout['mb4']);
        $this->assertSame(['column' => 1, 'columns' => 2], $layout['ma2']);
    }

    public function test_partial_overlap_also_counts_as_overlap(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 1, 'start' => 600, 'end' => 705],
            ['id' => 2, 'start' => 660, 'end' => 765],
        ]);

        $this->assertSame(2, $layout[1]['columns']);
        $this->assertSame(1, $layout[2]['column']);
    }

    public function test_three_on_top_of_each_other_each_get_a_third(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 1, 'start' => 600, 'end' => 705],
            ['id' => 2, 'start' => 600, 'end' => 705],
            ['id' => 3, 'start' => 600, 'end' => 705],
        ]);

        $this->assertSame([0, 1, 2], [$layout[1]['column'], $layout[2]['column'], $layout[3]['column']]);
        $this->assertSame([3, 3, 3], [$layout[1]['columns'], $layout[2]['columns'], $layout[3]['columns']]);
    }

    public function test_a_group_shares_the_same_width_so_the_cards_line_up(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 1, 'start' => 600, 'end' => 700],
            ['id' => 2, 'start' => 650, 'end' => 750],
            ['id' => 3, 'start' => 720, 'end' => 800],
        ]);

        $this->assertSame([2, 2, 2], [$layout[1]['columns'], $layout[2]['columns'], $layout[3]['columns']]);
        $this->assertSame(0, $layout[3]['column']);
    }

    public function test_a_separate_group_later_in_the_day_is_full_width_again(): void
    {
        $layout = Timetable::sideBySide([
            ['id' => 1, 'start' => 540, 'end' => 645],
            ['id' => 2, 'start' => 540, 'end' => 645],
            ['id' => 3, 'start' => 900, 'end' => 1005],
        ]);

        $this->assertSame(2, $layout[1]['columns']);
        $this->assertSame(1, $layout[3]['columns']);
    }

    public function test_an_empty_list_gives_an_empty_layout(): void
    {
        $this->assertSame([], Timetable::sideBySide([]));
    }

    public function test_pixel_measurements(): void
    {
        $this->assertSame(600, Timetable::minutes('10:00:00'));
        $this->assertSame(144.0, Timetable::top('10:00'));
        $this->assertSame(42.0, Timetable::height(10));
        $this->assertSame(126.0, Timetable::heightBetween('10:00', '11:45'));
        $this->assertSame(72.0, Timetable::heightFrom('10:00', null));
        $this->assertStringContainsString('data-timetable-start="480"', Timetable::attributes());
    }
}
