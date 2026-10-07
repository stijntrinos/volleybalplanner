<?php

namespace App\Planner\Models;

use App\Planner\Enums\VolleystarsRole;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VolleystarsTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolleystarsTask extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VolleystarsTaskFactory> */
    use HasFactory;

    protected $table = 'planner_volleystars_tasks';

    protected $fillable = ['block_id', 'role', 'team_id', 'court_id', 'sort_order'];

    protected function casts(): array
    {
        return ['role' => VolleystarsRole::class];
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
