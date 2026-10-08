<?php

namespace App\Models;

use App\Enums\ContactTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContactRequest extends Model
{
    protected $fillable = ['topic', 'name', 'club', 'email', 'phone', 'teams', 'venues', 'message'];

    protected function casts(): array
    {
        return [
            'topic' => ContactTopic::class,
            'handled_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('handled_at');
    }
}
