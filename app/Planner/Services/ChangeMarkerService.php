<?php

namespace App\Planner\Services;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Models\FixtureMatch;
use DomainException;

/**
 * Marks a home match as "has to move" without knowing where to yet. That is
 * not a request: there is no new place to get approval for. The match stays
 * where it is and appears in the list of matches still to plan, until someone
 * moves it or removes the marker.
 */
class ChangeMarkerService
{
    public function mark(FixtureMatch $match, ?int $userId = null): void
    {
        $this->requireDraftHomeMatch($match);
        if ($match->change_requested || $match->change_status === ChangeStatus::Requested) {
            throw new DomainException(__('Voor deze wedstrijd loopt al een wijzigingsaanvraag.'));
        }

        $match->update([
            'change_status' => ChangeStatus::ToChange,
            'change_requested_at' => now(),
            'change_requested_by' => $userId,
        ]);

        activity('planner-move')->performedOn($match)->causedBy($userId)
            ->log('Marked as to be moved');
    }

    public function clear(FixtureMatch $match, ?int $userId = null): void
    {
        $this->requireDraftHomeMatch($match);
        if ($match->change_status !== ChangeStatus::ToChange) {
            return;
        }

        $match->update([
            'change_status' => null,
            'change_requested_at' => null,
            'change_requested_by' => null,
        ]);

        activity('planner-move')->performedOn($match)->causedBy($userId)
            ->log('To-be-moved marker removed');
    }

    private function requireDraftHomeMatch(FixtureMatch $match): void
    {
        if (!$match->version->isDraft()) {
            throw new DomainException(__('Alleen in een conceptplanning kan een wedstrijd worden gemarkeerd.'));
        }
        if ($match->match_type !== MatchType::Home) {
            throw new DomainException(__('Alleen een thuiswedstrijd kan in de planner worden gemarkeerd.'));
        }
    }
}
