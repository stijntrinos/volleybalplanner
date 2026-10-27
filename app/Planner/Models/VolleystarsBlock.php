<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VolleystarsBlockFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $version_id
 * @property Carbon $date
 * @property int $venue_id
 * @property string $setup_start
 * @property string $setup_end
 * @property string $teardown_start
 * @property string $teardown_end
 * @property string $scoring_start
 * @property string $scoring_end
 * @property string $refereeing_start
 * @property string $refereeing_end
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Collection<int, VolleystarsTask> $tasks
 * @property-read int|null $tasks_count
 * @property-read Venue $venue
 * @property-read ScheduleVersion $version
 *
 * @method static \Database\Factories\Planner\VolleystarsBlockFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereScoringEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereScoringStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereSetupEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereSetupStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereTeardownEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereTeardownStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsBlock whereVersionId($value)
 *
 * @mixin \Eloquent
 */
class VolleystarsBlock extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VolleystarsBlockFactory> */
    use HasFactory;

    protected $table = 'planner_volleystars_blocks';

    protected $fillable = [
        'version_id', 'date', 'venue_id', 'setup_start', 'setup_end', 'teardown_start',
        'teardown_end', 'scoring_start', 'scoring_end', 'refereeing_start', 'refereeing_end', 'notes',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    protected static function newFactory(): VolleystarsBlockFactory
    {
        return VolleystarsBlockFactory::new();
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

    /** @return HasMany<VolleystarsTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(VolleystarsTask::class, 'block_id');
    }
}
