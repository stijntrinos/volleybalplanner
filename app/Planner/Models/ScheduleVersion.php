<?php

namespace App\Planner\Models;

use App\Models\User;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ScheduleVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
