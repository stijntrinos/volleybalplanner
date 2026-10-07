<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A club calendar entry (holiday, tournament, ...) spanning one or more days. */
class CalendarEvent extends Model
{
    use BelongsToClub;

    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory;

    protected $table = 'planner_calendar_events';

    protected $fillable = ['start_date', 'end_date', 'name', 'type'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function newFactory(): CalendarEventFactory
    {
        return CalendarEventFactory::new();
    }
}
