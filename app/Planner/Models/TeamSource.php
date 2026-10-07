<?php

namespace App\Planner\Models;

use App\Planner\Enums\TeamSourceType;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TeamSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A Nevobo feed of a team; the type tells the import which feeds hold a schedule. */
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
