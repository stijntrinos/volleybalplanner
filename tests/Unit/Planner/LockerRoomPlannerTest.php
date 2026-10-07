<?php

namespace Tests\Unit\Planner;

use App\Planner\Services\LockerRoomPlanner;
use Tests\TestCase;

class LockerRoomPlannerTest extends TestCase
{
    public function test_locker_room_planner_respects_category_and_time_buffer(): void
    {
        $plan = (new LockerRoomPlanner)->plan([
            ['id' => 1, 'start' => '10:00', 'end' => '11:30', 'home' => 'Club DS 1', 'away' => 'Orion DS 2'],
            ['id' => 2, 'start' => '10:30', 'end' => '12:00', 'home' => 'Club DS 2', 'away' => 'Orion DS 3'],
            ['id' => 3, 'start' => '10:00', 'end' => '11:30', 'home' => 'Club HS 1', 'away' => 'Orion HS 2'],
        ]);

        $this->assertSame(['home' => 3, 'away' => 5], $plan['assignments'][1]);
        $this->assertSame(['home' => 4, 'away' => 6], $plan['assignments'][2]);
        $this->assertSame(['home' => 1, 'away' => 2], $plan['assignments'][3]);
        $this->assertSame([], $plan['warnings']);
    }

    public function test_a_full_room_gives_a_warning_and_the_category_overrides_the_name(): void
    {
        $plan = (new LockerRoomPlanner)->plan([
            ['id' => 1, 'start' => '10:00', 'end' => '11:30', 'home' => 'Club HS 1', 'away' => 'Orion HS 2'],
            ['id' => 2, 'start' => '11:00', 'end' => '12:30', 'home' => 'Club Recreanten', 'away' => 'Orion DS 1', 'home_category' => 'boys_men'],
        ]);

        $this->assertSame(1, $plan['assignments'][2]['home']);
        $this->assertSame([[
            'match_id' => 2, 'side' => 'home', 'team' => 'Club Recreanten', 'room' => 1,
            'reason' => 'Kleedkamer 1 is op dit tijdsblok bezet.',
        ]], $plan['warnings']);
    }
}
