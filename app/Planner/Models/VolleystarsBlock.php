<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VolleystarsBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VolleystarsBlock extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VolleystarsBlockFactory> */
    use HasFactory;

    protected $table = 'planner_volleystars_blocks';

    protected $fillable = [
        'version_id', 'date', 'venue_id', 'setup_start', 'setup_end', 'teardown_start',
        'teardown_end', 'scoring_start', 'scoring_end', 'notes',
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
