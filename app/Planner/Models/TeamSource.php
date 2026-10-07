<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\TeamSourceType;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TeamSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Nevobo feed of a team; the type tells the import which feeds hold a schedule.
 *
 * @property int $id
 * @property int $club_id
 * @property int $team_id
 * @property string $name
 * @property string $url
 * @property TeamSourceType $type
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $last_succeeded_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Team $team
 *
 * @method static \Database\Factories\Planner\TeamSourceFactory factory($count = null, $state = [])
 * @method static Builder<static>|TeamSource forImport()
 * @method static Builder<static>|TeamSource newModelQuery()
 * @method static Builder<static>|TeamSource newQuery()
 * @method static Builder<static>|TeamSource ordered()
 * @method static Builder<static>|TeamSource query()
 * @method static Builder<static>|TeamSource whereClubId($value)
 * @method static Builder<static>|TeamSource whereCreatedAt($value)
 * @method static Builder<static>|TeamSource whereId($value)
 * @method static Builder<static>|TeamSource whereIsActive($value)
 * @method static Builder<static>|TeamSource whereLastCheckedAt($value)
 * @method static Builder<static>|TeamSource whereLastError($value)
 * @method static Builder<static>|TeamSource whereLastSucceededAt($value)
 * @method static Builder<static>|TeamSource whereName($value)
 * @method static Builder<static>|TeamSource whereSortOrder($value)
 * @method static Builder<static>|TeamSource whereTeamId($value)
 * @method static Builder<static>|TeamSource whereType($value)
 * @method static Builder<static>|TeamSource whereUpdatedAt($value)
 * @method static Builder<static>|TeamSource whereUrl($value)
 *
 * @mixin \Eloquent
 */
class TeamSource extends Model
{
    use BelongsToClub;

    /** @use HasFactory<TeamSourceFactory> */
    use HasFactory;

    protected $table = 'planner_team_sources';

    protected $fillable = [
        'team_id', 'name', 'url', 'type', 'sort_order', 'is_active',
        'last_checked_at', 'last_succeeded_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'type' => TeamSourceType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'last_checked_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): TeamSourceFactory
    {
        return TeamSourceFactory::new();
    }

    /** The order in which a team's sources are listed. */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * The feeds the planner reads matches from. A standings feed holds no
     * matches and would pollute the planning.
     */
    #[Scope]
    protected function forImport(Builder $query): void
    {
        $query->where('is_active', true)->where('type', TeamSourceType::Schedule);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
