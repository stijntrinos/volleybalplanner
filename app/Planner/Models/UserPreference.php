<?php

namespace App\Planner\Models;

use App\Models\User;
use App\Planner\Enums\OverlapPolicy;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\UserPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately without a policy: every lookup goes through
 * user_id = auth()->id(), so there is nothing to grant to anyone else.
 */
class UserPreference extends Model
{
    use BelongsToClub;

    /** @use HasFactory<UserPreferenceFactory> */
    use HasFactory;

    protected $table = 'planner_user_preferences';

    protected $fillable = [
        'user_id', 'default_venue_id', 'active_version_id', 'selected_date',
        'hidden_venues', 'nevobo_grouping', 'overlap_policy', 'publish_with_conflicts',
        'show_trainings', 'duties_ask_reason', 'duties_validation_mode',
    ];

    protected function casts(): array
    {
        return [
            'overlap_policy' => OverlapPolicy::class,
            'selected_date' => 'date',
            'hidden_venues' => 'array',
            'publish_with_conflicts' => 'boolean',
            'show_trainings' => 'boolean',
            'duties_ask_reason' => 'boolean',
            'duties_validation_mode' => 'boolean',
        ];
    }

    protected static function newFactory(): UserPreferenceFactory
    {
        return UserPreferenceFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function defaultVenue(): BelongsTo
    {
        return $this->belongsTo(Venue::class, 'default_venue_id');
    }

    /** @return BelongsTo<ScheduleVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(ScheduleVersion::class, 'active_version_id');
    }
}
