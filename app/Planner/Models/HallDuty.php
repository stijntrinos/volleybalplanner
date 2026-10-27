<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Services\Duties\DutyPoints;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\HallDutyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $version_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property int $team_id
 * @property bool $team_auto
 * @property int $venue_id
 * @property string|null $notes
 * @property int|null $points fixed when the duty came about
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Team $team
 * @property-read Venue $venue
 * @property-read ScheduleVersion $version
 *
 * @method static \Database\Factories\Planner\HallDutyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereTeamAuto($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HallDuty whereVersionId($value)
 *
 * @mixin \Eloquent
 */
class HallDuty extends Model
{
    use BelongsToClub;

    /** @use HasFactory<HallDutyFactory> */
    use HasFactory;

    protected $table = 'planner_hall_duties';

    protected $fillable = ['version_id', 'date', 'start_time', 'end_time', 'team_id', 'team_auto', 'venue_id', 'notes', 'points'];

    protected function casts(): array
    {
        return ['date' => 'date', 'team_auto' => 'boolean', 'points' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (HallDuty $duty) => DutyPoints::fillHallDuty($duty));
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
