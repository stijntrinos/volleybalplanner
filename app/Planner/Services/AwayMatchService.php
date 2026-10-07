<?php

namespace App\Planner\Services;

use App\Planner\Enums\AwayChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** The change workflow of away matches (module away_matches). */
class AwayMatchService
{
    /** Pseudo status: the opponent's proposal is rejected and cleared; the match keeps its schedule. */
    public const STATUS_REJECTED = 'rejected';

    public function __construct(private readonly ConflictEngine $conflictEngine) {}

    /**
     * @param  array<string, mixed>  $data  away_change_status (AwayChangeStatus value or 'rejected'),
     *                                      away_new_date, away_new_time, away_new_venue, away_notes
     */
    public function update(FixtureMatch $match, array $data, ?int $userId = null): FixtureMatch
    {
        if ($match->match_type !== MatchType::Away) {
            throw new DomainException(__('Alleen uitwedstrijden kunnen via deze workflow worden gewijzigd.'));
        }

        $status = $data['away_change_status'] ?? AwayChangeStatus::None->value;
        $status = $status instanceof AwayChangeStatus ? $status->value : (string) $status;
        if (AwayChangeStatus::tryFrom($status) === null && $status !== self::STATUS_REJECTED) {
            throw new DomainException(__('De gekozen wijzigingsstatus is niet geldig.'));
        }

        return DB::transaction(function () use ($match, $data, $status, $userId): FixtureMatch {
            $fields = ['away_change_status', 'away_new_date', 'away_new_time', 'away_new_venue', 'away_notes'];
            $old = $match->only($fields);
            $isFinal = $status === AwayChangeStatus::Final->value;
            $updates = [
                'away_change_status' => $isFinal || $status === self::STATUS_REJECTED ? AwayChangeStatus::None : AwayChangeStatus::from($status),
                'away_new_date' => $data['away_new_date'] ?? null,
                'away_new_time' => $data['away_new_time'] ?? null,
                'away_new_venue' => $data['away_new_venue'] ?? null,
                'away_notes' => $data['away_notes'] ?? null,
            ];
            if ($isFinal) {
                $updates += [
                    'date' => ($data['away_new_date'] ?? null) ?: $match->date,
                    'time' => ($data['away_new_time'] ?? null) ?: $match->time,
                    'original_venue' => ($data['away_new_venue'] ?? null) ?: $match->original_venue,
                ];
                $updates['away_new_date'] = $updates['away_new_time'] = $updates['away_new_venue'] = null;
            } elseif ($status === self::STATUS_REJECTED || $status === AwayChangeStatus::None->value) {
                $updates['away_new_date'] = $updates['away_new_time'] = $updates['away_new_venue'] = null;
            }
            $match->update($updates);

            activity('planner-away-match')
                ->performedOn($match)
                ->causedBy($userId)
                ->withProperties(['old' => $old, 'new' => $match->only($fields)])
                ->log('Away match status changed');

            return $match->refresh();
        });
    }

    /**
     * For an away match we have no court to give away; the only question is
     * whether the team is already busy at that moment. That weighing lives in
     * the ConflictEngine, including recognising team names written slightly
     * differently.
     *
     * @param  Collection<int, FixtureMatch>|null  $matches  the version's schedule, preloaded by a list view
     * @return list<string>
     */
    public function conflicts(FixtureMatch $match, ?Collection $matches = null): array
    {
        $inProgress = $match->away_change_status === AwayChangeStatus::InProgress;
        $date = $inProgress && $match->away_new_date
            ? $match->away_new_date->toDateString()
            : $match->date?->toDateString();
        $time = substr((string) ($inProgress && $match->away_new_time ? $match->away_new_time : $match->time), 0, 5);
        if (!$date || !$time) {
            return [];
        }

        $findings = $this->conflictEngine->teamSchedule($match, $date, $time, $match->duration_minutes ?: 105, null, $matches);

        return array_values(array_unique(array_column($findings, 'message')));
    }
}
