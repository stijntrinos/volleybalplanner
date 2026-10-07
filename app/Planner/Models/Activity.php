<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A non-match activity on the day schedule of a version. */
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
