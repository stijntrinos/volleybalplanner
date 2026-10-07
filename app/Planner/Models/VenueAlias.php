<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\VenueAliasFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Another name under which a venue appears in imports. */
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
