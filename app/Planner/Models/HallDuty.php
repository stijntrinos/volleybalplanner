<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\HallDutyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HallDuty extends Model
{
    use BelongsToClub;

    /** @use HasFactory<HallDutyFactory> */
    use HasFactory;

    protected $table = 'planner_hall_duties';

    protected $fillable = ['version_id', 'date', 'start_time', 'end_time', 'team_id', 'team_auto', 'venue_id', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'team_auto' => 'boolean'];
    }

    protected static function newFactory(): HallDutyFactory
    {
        return HallDutyFactory::new();
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
