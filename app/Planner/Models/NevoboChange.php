<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\NevoboChangeField;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\NevoboChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Records that a Nevobo change to one field of a match has been processed.
 *
 * @property int $id
 * @property int $club_id
 * @property int $version_id
 * @property int $match_id
 * @property NevoboChangeField $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property bool $is_processed
 * @property Carbon|null $processed_at
 * @property int|null $processed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read FixtureMatch $match
 * @property-read User|null $processedBy
 * @property-read ScheduleVersion $version
 *
 * @method static \Database\Factories\Planner\NevoboChangeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereField($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereIsProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereNewValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereOldValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereProcessedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NevoboChange whereVersionId($value)
 *
 * @mixin \Eloquent
 */
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
