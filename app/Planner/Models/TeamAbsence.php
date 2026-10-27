<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\TeamAbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A period in which a team cannot do duties: a tournament, a camp or a
 * holiday. The duty planning does not propose the team then, but the
 * planning committee can still choose it.
 *
 * @property int $id
 * @property int $club_id
 * @property int $team_id
 * @property Carbon $date_from
 * @property Carbon|null $date_until
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read Team $team
 *
 * @method static \Database\Factories\Planner\TeamAbsenceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class TeamAbsence extends Model
{
    use BelongsToClub;

    /** @use HasFactory<TeamAbsenceFactory> */
    use HasFactory;

    protected $table = 'planner_team_absences';

    protected $fillable = ['team_id', 'date_from', 'date_until', 'start_time', 'end_time', 'reason'];

    protected function casts(): array
    {
        return ['date_from' => 'date', 'date_until' => 'date'];
    }

    protected static function newFactory(): TeamAbsenceFactory
    {
        return TeamAbsenceFactory::new();
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Whether the absence touches this duty. Without times it covers the
     * whole day; with times only what falls within them.
     */
    public function covers(string $date, string $start, string $end): bool
    {
        $until = ($this->date_until ?? $this->date_from)->toDateString();
        if ($date < $this->date_from->toDateString() || $date > $until) {
            return false;
        }
        if (blank($this->start_time) || blank($this->end_time)) {
            return true;
        }

        return $start < substr((string) $this->end_time, 0, 5) && substr((string) $this->start_time, 0, 5) < $end;
    }

    /** How the team choice describes it. */
    public function description(): string
    {
        return filled($this->reason)
            ? __('Afgemeld: :reason', ['reason' => $this->reason])
            : __('Afgemeld voor deze dag');
    }
}
