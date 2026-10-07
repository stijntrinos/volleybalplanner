<?php

namespace App\Planner\Models;

use App\Models\User;
use App\Planner\Enums\NevoboChangeField;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\NevoboChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Records that a Nevobo change to one field of a match has been processed. */
class NevoboChange extends Model
{
    use BelongsToClub;

    /** @use HasFactory<NevoboChangeFactory> */
    use HasFactory;

    protected $table = 'planner_nevobo_changes';

    protected $fillable = [
        'version_id', 'match_id', 'field', 'old_value', 'new_value', 'is_processed', 'processed_at', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'field' => NevoboChangeField::class,
            'is_processed' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): NevoboChangeFactory
    {
        return NevoboChangeFactory::new();
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'version_id');
    }

    /** @return BelongsTo<FixtureMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class, 'match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
