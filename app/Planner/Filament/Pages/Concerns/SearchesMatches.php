<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\FixtureMatch;
use App\Planner\Support\DateFormat;

/**
 * Searching matches and jumping to a result.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait SearchesMatches
{
    public string $search = '';

    /** @return list<array<string, mixed>> */
    public function getSearchResultsProperty(): array
    {
        $search = trim($this->search);

        if (!$this->versionId || mb_strlen($search) < 2) {
            return [];
        }

        return FixtureMatch::query()
            ->where('version_id', $this->versionId)
            ->whereNotNull('date')
            ->where(function ($query) use ($search): void {
                $query->where('match_code', 'like', "%{$search}%")
                    ->orWhere('home_team_name', 'like', "%{$search}%")
                    ->orWhere('away_team_name', 'like', "%{$search}%");
            })
            ->with(['venue:id,name', 'court:id,name'])
            ->orderBy('date')
            ->orderBy('time')
            ->limit(25)
            ->get()
            ->map(fn (FixtureMatch $match): array => [
                'id' => $match->id,
                'code' => $match->match_code,
                'date' => $match->date?->toDateString(),
                'date_label' => DateFormat::withWeekday($match->date, short: true),
                'time' => substr((string) $match->time, 0, 5),
                'home_team' => $match->home_team_name,
                'away_team' => $match->away_team_name,
                'venue' => $match->venue?->name,
                'court' => $match->court?->name,
            ])
            ->all();
    }

    public function selectSearchResult(int $matchId): void
    {
        $match = FixtureMatch::query()
            ->where('version_id', $this->versionId)
            ->whereNotNull('date')
            ->findOrFail($matchId);

        $this->date = $match->date->toDateString();
        $this->search = '';
        $this->savePlannerPreference();
        $this->load();
        // After loading: that collapses the venues of the new day, and the
        // venue of the chosen result should be open.
        $this->hiddenVenues = array_values(array_diff($this->hiddenVenues, [$match->venue_id]));
        $this->dispatch('planner-search-result-selected', matchId: $match->id);
    }
}
