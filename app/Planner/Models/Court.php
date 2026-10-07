<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Planner\Enums\Source;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\CourtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $club_id
 * @property int $venue_id
 * @property string $name
 * @property bool $is_training_court
 * @property Source $source
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Venue $venue
 *
 * @method static \Database\Factories\Planner\CourtFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereIsTrainingCourt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Court whereVenueId($value)
 *
 * @mixin \Eloquent
 */
class Court extends Model
{
    use BelongsToClub;

    /** @use HasFactory<CourtFactory> */
    use HasFactory;

    protected $table = 'planner_courts';

    protected $fillable = ['venue_id', 'name', 'is_training_court', 'source', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_training_court' => 'boolean',
            'is_active' => 'boolean',
            'source' => Source::class,
        ];
    }

    protected static function newFactory(): CourtFactory
    {
        return CourtFactory::new();
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
