<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Services\Duties\DutyPoints;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VolleystarsTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $block_id
 * @property VolleystarsRole $role
 * @property int|null $team_id
 * @property int|null $court_id
 * @property int $sort_order
 * @property int|null $points fixed when the duty came about
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read VolleystarsBlock $block
 * @property-read Club $club
 * @property-read Court|null $court
 * @property-read Team|null $team
 *
 * @method static \Database\Factories\Planner\VolleystarsTaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereBlockId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereCourtId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VolleystarsTask whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class VolleystarsTask extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VolleystarsTaskFactory> */
    use HasFactory;

    protected $table = 'planner_volleystars_tasks';

    protected $fillable = ['block_id', 'role', 'team_id', 'court_id', 'sort_order', 'points'];

    protected function casts(): array
    {
        return ['role' => VolleystarsRole::class, 'points' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (VolleystarsTask $task) => DutyPoints::fillVolleystarsTask($task));
    }

    protected static function newFactory(): VolleystarsTaskFactory
    {
        return VolleystarsTaskFactory::new();
    }

    /** @return BelongsTo<VolleystarsBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(VolleystarsBlock::class, 'block_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
