<?php

namespace App\Models;

use App\Planner\Support\ClubRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation to a club, waiting for the invitee to accept it.
 *
 * @property int $id
 * @property int $club_id
 * @property string $email
 * @property string $name
 * @property ClubRole $role
 * @property string $token_hash
 * @property int|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read Club $club
 * @property-read User|null $inviter
 */
class Invitation extends Model
{
    /** How long an invitation link works. */
    public const VALID_DAYS = 7;

    protected $table = 'club_invitations';

    protected $fillable = ['club_id', 'email', 'name', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'role' => ClubRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The pending invitation behind a link, or null when it is unknown, used or expired. */
    public static function findPendingByToken(string $token): ?self
    {
        return self::query()->pending()->with('club')->where('token_hash', self::hashToken($token))->first();
    }
}
