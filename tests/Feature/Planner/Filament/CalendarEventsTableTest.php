<?php

namespace Tests\Feature\Planner\Filament;

use App\Planner\Filament\Resources\CalendarEvents\Tables\CalendarEventsTable;
use App\Planner\Models\CalendarEvent;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Ported from Aetos KalenderActiviteitenTableTest. */
class CalendarEventsTableTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_single_and_multi_day_periods_get_a_readable_duration(): void
    {
        $oneDay = new CalendarEvent(['start_date' => '2026-08-10', 'end_date' => '2026-08-10']);
        $week = new CalendarEvent(['start_date' => '2026-08-10', 'end_date' => '2026-08-16']);

        $this->assertSame('1 dag', CalendarEventsTable::durationLabel($oneDay));
        $this->assertSame('7 dagen', CalendarEventsTable::durationLabel($week));
        $this->assertSame('Ma. 10-08-2026', CalendarEventsTable::periodLabel($oneDay));
    }

    public function test_events_get_a_status_relative_to_today(): void
    {
        Carbon::setTestNow('2026-08-10 09:00:00');

        $past = new CalendarEvent(['start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $ongoing = new CalendarEvent(['start_date' => '2026-08-09', 'end_date' => '2026-08-11']);
        $soon = new CalendarEvent(['start_date' => '2026-08-14', 'end_date' => '2026-08-14']);

        $this->assertSame('Afgelopen', CalendarEventsTable::momentLabel($past));
        $this->assertSame('Bezig', CalendarEventsTable::momentLabel($ongoing));
        $this->assertSame('Over 4 dagen', CalendarEventsTable::momentLabel($soon));
    }
}
