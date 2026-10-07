<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\CalendarEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A club calendar entry (holiday, tournament, .
 *
 * ..) spanning one or more days.
 *
 * @property int $id
 * @property int $club_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $name
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read string|null $type_color Only when selected: the subquery in CalendarEventResource::getEloquentQuery()
 *
 * @method static \Database\Factories\Planner\CalendarEventFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CalendarEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
