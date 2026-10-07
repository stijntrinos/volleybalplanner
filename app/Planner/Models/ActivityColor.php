<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ActivityColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The colour of a calendar event type.
 *
 * @property int $id
 * @property int $club_id
 * @property string $type
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 *
 * @method static \Database\Factories\Planner\ActivityColorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ActivityColor whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ActivityColor extends Model
{
    use BelongsToClub;

    /** @use HasFactory<ActivityColorFactory> */
    use HasFactory;

    protected $table = 'planner_activity_colors';

    protected $fillable = ['type', 'color'];

    protected static function newFactory(): ActivityColorFactory
    {
        return ActivityColorFactory::new();
    }
}
