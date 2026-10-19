<?php

namespace App\Models;

use App\Enums\ContactTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ContactTopic $topic
 * @property string $name
 * @property string $club
 * @property string $email
 * @property string|null $phone
 * @property string|null $teams
 * @property string|null $venues
 * @property string|null $message
 * @property int|null $demo_club_id
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club|null $demoClub
 */
class ContactRequest extends Model
{
    use MassPrunable;

    protected $fillable = ['topic', 'name', 'club', 'email', 'phone', 'teams', 'venues', 'message'];

    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Personal data from the contact form (AVG): handled requests go after a
     * year, unhandled ones after two.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where(fn (Builder $query) => $query
            ->where('handled_at', '<', now()->subYear())
            ->orWhere('created_at', '<', now()->subYears(2)));
    }

    /** @return BelongsTo<Club, $this> */
    public function demoClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'demo_club_id');
    }

    public function isDemoRequest(): bool
    {
        return $this->topic === ContactTopic::Demo;
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
