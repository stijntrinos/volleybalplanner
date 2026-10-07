<?php

namespace App\Planner\Models;

use App\Planner\Enums\AvailabilityScope;
use App\Planner\Enums\AvailabilityStatus;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\AvailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    use BelongsToClub;

    /** @use HasFactory<AvailabilityFactory> */
    use HasFactory;

    protected $table = 'planner_availabilities';

    protected $fillable = [
        'venue_id', 'court_id', 'scope', 'date', 'weekday', 'period_start', 'period_end',
        'start_time', 'end_time', 'status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'scope' => AvailabilityScope::class,
            'status' => AvailabilityStatus::class,
            'date' => 'date',
            'weekday' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    protected static function newFactory(): AvailabilityFactory
    {
        return AvailabilityFactory::new();
    }

    /**
     * A rule without times applies to the whole day. With times, "available"
     * means the venue is only rented within that window, while "closed" or
     * "partial" means the restriction applies within it.
     */
    public function hasTimeWindow(): bool
    {
        return filled($this->start_time) && filled($this->end_time);
    }

    public function window(): string
    {
        return $this->hasTimeWindow()
            ? substr((string) $this->start_time, 0, 5).'–'.substr((string) $this->end_time, 0, 5)
            : __('hele dag');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Court, $this> */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
