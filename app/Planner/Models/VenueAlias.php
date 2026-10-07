<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VenueAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Another name under which a venue appears in imports.
 *
 * @property int $id
 * @property int $club_id
 * @property int $venue_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Venue $venue
 *
 * @method static \Database\Factories\Planner\VenueAliasFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VenueAlias whereVenueId($value)
 *
 * @mixin \Eloquent
 */
class VenueAlias extends Model
{
    use BelongsToClub;

    /** @use HasFactory<VenueAliasFactory> */
    use HasFactory;

    protected $table = 'planner_venue_aliases';

    protected $fillable = ['venue_id', 'name'];

    protected static function newFactory(): VenueAliasFactory
    {
        return VenueAliasFactory::new();
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
