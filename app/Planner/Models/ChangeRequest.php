<?php

namespace App\Planner\Models;

use App\Models\Club;
use App\Models\User;
use App\Planner\Enums\ChangeReason;
use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Support\DateFormat;
use App\Support\Tenancy\BelongsToClub;
use Database\Factories\Planner\ChangeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request from a team contact to change a match or a duty, submitted via a
 * shared link. It records what was asked and what the planning committee
 * decided; the actual change happens in the planner, so there are never two
 * places that manage the planning.
 *
 * @property int $id
 * @property int $club_id
 * @property int|null $match_id
 * @property int|null $hall_duty_id
 * @property string|null $match_code
 * @property int|null $team_id
 * @property ChangeRequestKind $kind
 * @property DutyType|null $duty_type
 * @property Carbon|null $duty_date
 * @property string|null $duty_time
 * @property string|null $duty_location
 * @property string|null $duty_description
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property ContactPreference $contact_preference
 * @property string $reason
 * @property string|null $reason_other
 * @property string $explanation
 * @property Carbon|null $proposed_date
 * @property string|null $proposed_time
 * @property ChangeRequestStatus $status
 * @property string|null $note
 * @property Carbon|null $handled_at
 * @property int|null $handled_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Club $club
 * @property-read HallDuty|null $hallDuty
 * @property-read User|null $handledBy
 * @property-read FixtureMatch|null $match
 * @property-read Team|null $team
 *
 * @method static \Database\Factories\Planner\ChangeRequestFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereClubId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereContactPreference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereDutyDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereDutyDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereDutyLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereDutyTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereDutyType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereHallDutyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereHandledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereHandledBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereMatchCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereProposedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereProposedTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereReasonOther($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChangeRequest whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ChangeRequest extends Model
{
    use BelongsToClub;

    /** @use HasFactory<ChangeRequestFactory> */
    use HasFactory;

    protected $table = 'planner_change_requests';

    protected $fillable = [
        'match_id', 'match_code', 'hall_duty_id', 'team_id',
        'kind', 'duty_type', 'duty_date', 'duty_time', 'duty_location', 'duty_description',
        'name', 'email', 'phone', 'contact_preference', 'reason', 'reason_other', 'explanation',
        'proposed_date', 'proposed_time',
        'status', 'note', 'handled_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            // A volunteer's contact details must not be readable in the database.
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'kind' => ChangeRequestKind::class,
            'duty_type' => DutyType::class,
            'contact_preference' => ContactPreference::class,
            'status' => ChangeRequestStatus::class,
            'handled_at' => 'datetime',
            'duty_date' => 'date',
            'proposed_date' => 'date',
        ];
    }

    protected static function newFactory(): ChangeRequestFactory
    {
        return ChangeRequestFactory::new();
    }

    public function isOpen(): bool
    {
        return $this->status === ChangeRequestStatus::Open;
    }

    public function isParked(): bool
    {
        return $this->status === ChangeRequestStatus::Parked;
    }

    public function isDuty(): bool
    {
        return $this->kind === ChangeRequestKind::Duty;
    }

    /** How the requester wants to be contacted, with the phone number when there is one. */
    public function contactText(): string
    {
        $preference = $this->contact_preference ?? ContactPreference::Email;

        return $preference === ContactPreference::Whatsapp && filled($this->phone)
            ? $preference->getLabel().' ('.$this->phone.')'
            : $preference->getLabel();
    }

    /** The moment the requester proposes, or null when nothing was proposed. */
    public function proposalText(): ?string
    {
        if (!$this->proposed_date) {
            return null;
        }

        return trim(DateFormat::withWeekday($this->proposed_date).' '
            .($this->proposed_time ? __('om :time', ['time' => $this->proposed_time]) : ''));
    }

    /** The reason as displayed: for "other" the actual reason is in the separate field. */
    public function reasonText(): string
    {
        return $this->reason === ChangeReason::Other->value
            ? (string) ($this->reason_other ?: ChangeReason::Other->getLabel())
            : (string) $this->reason;
    }

    /**
     * The match as displayed. When the match has disappeared (cleaning up a
     * version can do that) the code remains.
     */
    public function matchDescription(): string
    {
        $match = $this->match;

        if (!$match instanceof FixtureMatch) {
            return (string) ($this->match_code ?: __('Wedstrijd onbekend'));
        }

        return trim($match->home_team_name.' – '.$match->away_team_name, ' –')
            ?: (string) ($this->match_code ?: __('Wedstrijd onbekend'));
    }

    /** The chosen subject, even when the original planning no longer exists. */
    public function subjectDescription(): string
    {
        if (!$this->isDuty()) {
            return $this->matchDescription();
        }

        $duty = $this->duty_type?->getLabel() ?? ChangeRequestKind::Duty->getLabel();

        return $duty.($this->duty_description ? ' · '.$this->duty_description : '');
    }

    /** @return BelongsTo<FixtureMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class, 'match_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<HallDuty, $this> */
    public function hallDuty(): BelongsTo
    {
        return $this->belongsTo(HallDuty::class);
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
