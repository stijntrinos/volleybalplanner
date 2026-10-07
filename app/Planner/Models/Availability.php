<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\AvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $venue_id
 * @property int|null $court_id
 * @property AvailabilityScope $scope
 * @property Carbon|null $date
 * @property int|null $weekday
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property string|null $start_time
 * @property string|null $end_time
 * @property AvailabilityStatus $status
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Court|null $court
 * @property-read Venue $venue
 *
 * @method static \Database\Factories\Planner\AvailabilityFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability wherePeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability wherePeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Availability whereWeekday($value)
 *
 * @mixin \Eloquent
 */
class Availability extends Model
{
    use BelongsToClub;

    /** @use HasFactory<AvailabilityFactory> */
    use HasFactory;

    protected $table = 'planner_availabilities';

    protected $fillable = [
        'venue_id', 'court_id', 'scope', 'date', 'weekday', 'period_start', 'period_end',
        'start_time', 'end_time', 'status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'scope' => AvailabilityScope::class,
            'status' => AvailabilityStatus::class,
            'date' => 'date',
            'weekday' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    protected static function newFactory(): AvailabilityFactory
    {
        return AvailabilityFactory::new();
    }

    /**
     * A rule without times applies to the whole day. With times, "available"
     * means the venue is only rented within that window, while "closed" or
     * "partial" means the restriction applies within it.
     */
    public function hasTimeWindow(): bool
    {
        return filled($this->start_time) && filled($this->end_time);
    }

    public function window(): string
    {
        return $this->hasTimeWindow()
            ? substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5)
            : __('hele dag');
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
