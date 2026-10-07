<?php

namespace App\Planner\Models;

use App\Planner\Enums\Source;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\CourtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
