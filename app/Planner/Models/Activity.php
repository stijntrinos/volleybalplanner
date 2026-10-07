<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A non-match activity on the day schedule of a version.
 *
 * @property int $id
 * @property int $club_id
 * @property int $version_id
 * @property Carbon $date
 * @property string $start_time
 * @property string|null $end_time
 * @property string $title
 * @property int|null $venue_id
 * @property int|null $court_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Court|null $court
 * @property-read Venue|null $venue
 * @property-read ScheduleVersion $version
 *
 * @method static \Database\Factories\Planner\ActivityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereVersionId($value)
 *
 * @mixin \Eloquent
 */
class Activity extends Model
{
    use BelongsToClub;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    protected $table = 'planner_activities';

    protected $fillable = ['version_id', 'date', 'start_time', 'end_time', 'title', 'venue_id', 'court_id', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function newFactory(): ActivityFactory
    {
        return ActivityFactory::new();
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
