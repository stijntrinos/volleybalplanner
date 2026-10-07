<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ScheduleVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property string|null $note
 * @property ScheduleVersionStatus $status
 * @property bool|null $is_published
 * @property int|null $created_by
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Club $club
 * @property-read User|null $creator
 * @property-read Collection<int, HallDuty> $hallDuties
 * @property-read int|null $hall_duties_count
 * @property-read Collection<int, FixtureMatch> $matches
 * @property-read int|null $matches_count
 * @property-read Collection<int, VolleystarsBlock> $volleystarsBlocks
 * @property-read int|null $volleystars_blocks_count
 *
 * @method static \Database\Factories\Planner\ScheduleVersionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereIsPublished($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ScheduleVersion whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ScheduleVersion extends Model
{
    use BelongsToClub;

    /** @use HasFactory<ScheduleVersionFactory> */
    use HasFactory;

    protected $table = 'planner_schedule_versions';

    protected $fillable = ['name', 'note', 'status', 'is_published', 'created_by', 'published_at', 'archived_at'];

    protected function casts(): array
    {
        return [
            'status' => ScheduleVersionStatus::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ScheduleVersionFactory
    {
        return ScheduleVersionFactory::new();
    }

    /** @return HasMany<FixtureMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class, 'version_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'version_id');
    }

    /** @return HasMany<HallDuty, $this> */
    public function hallDuties(): HasMany
    {
        return $this->hasMany(HallDuty::class, 'version_id');
    }

    /** @return HasMany<VolleystarsBlock, $this> */
    public function volleystarsBlocks(): HasMany
    {
        return $this->hasMany(VolleystarsBlock::class, 'version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === ScheduleVersionStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === ScheduleVersionStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === ScheduleVersionStatus::Archived;
    }
}
