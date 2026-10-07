<?php

namespace App\Planner\Models;

use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ActivityColorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** The colour of a calendar event type. */
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
