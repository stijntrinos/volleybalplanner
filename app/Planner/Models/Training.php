<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\Source;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TrainingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int|null $team_id
 * @property int $venue_id
 * @property string $weekday
 * @property string $start_time
 * @property string $end_time
 * @property int $court
 * @property string|null $court_type
 * @property string|null $notes
 * @property Source $source
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read User|null $creator
 * @property-read Team|null $team
 * @property-read Venue $venue
 *
 * @method static \Database\Factories\Planner\TrainingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCourt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCourtType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereVenueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereWeekday($value)
 *
 * @mixin \Eloquent
 */
class Training extends Model
{
    use BelongsToClub;

    /** @use HasFactory<TrainingFactory> */
    use HasFactory;

    /** How the weekday column is written; converting goes through Weekday. */
    public const WEEKDAYS = Weekday::NAMES;

    protected $table = 'planner_trainings';

    protected $fillable = [
        'team_id', 'venue_id', 'weekday', 'start_time', 'end_time', 'court', 'court_type', 'notes', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'court' => 'integer',
            'source' => Source::class,
        ];
    }

    protected static function newFactory(): TrainingFactory
    {
        return TrainingFactory::new();
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
