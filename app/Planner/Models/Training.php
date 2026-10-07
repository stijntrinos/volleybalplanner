<?php

namespace App\Planner\Models;

use App\Models\User;
use App\Planner\Enums\Source;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TrainingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Training extends Model
{
    use BelongsToClub;

    /** @use HasFactory<TrainingFactory> */
    use HasFactory;

    /** How the weekday column is written; converting goes through Weekday. */
    public const WEEKDAYS = Weekday::NAMES;

    protected $table = 'planner_trainings';

    protected $fillable = [
        'team_id', 'venue_id', 'weekday', 'start_time', 'end_time', 'court', 'court_type', 'notes', 'source', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'court' => 'integer',
            'source' => Source::class,
        ];
    }

    protected static function newFactory(): TrainingFactory
    {
        return TrainingFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
