<?php

namespace App\Planner\Services;

use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Models\VenueAlias;
use App\Planner\Support\VenueName;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dragging a match to another day is a proposal, not a fact. Until the
 * opponent and Nevobo agree the match stays at its old place, and so also on
 * the public overview, in the feeds and in the exports. The new place lives in
 * the provisional_* columns: as a marker in the planner, and as an occupied
 * court for whoever wants to put something else there.
 */
class ProvisionalMove
{
    /** The placement fields; prefixing provisional_ gives the proposal's column. */
    public const FIELDS = ['date', 'time', 'duration_minutes', 'venue_id', 'court_id', 'second_court_id'];

    /**
     * A move to another day waits for approval. Shifting on the same day, or
     * scheduling a match without a date, goes through directly.
     *
     * @param  array<string, mixed>  $placement
     */
    public function isProposal(FixtureMatch $match, array $placement): bool
    {
        return $match->date !== null
            && $match->date->toDateString() !== substr((string) ($placement['date'] ?? ''), 0, 10);
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed> the provisional_* columns for this placement
     */
    public function columns(array $placement): array
    {
        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $field): array => ['provisional_'.$field => $placement[$field] ?? null])
            ->all();
    }

    /** @return array<string, null> all provisional_* columns set to null */
    public function cleared(): array
    {
        return array_fill_keys(array_map(fn (string $field): string => 'provisional_'.$field, self::FIELDS), null);
    }

    /**
     * The proposal becomes the planning. The baseline moves along, because
     * this is what the opponent and Nevobo agreed to.
     *
     * @return array<string, mixed>
     */
    public function finalize(FixtureMatch $match): array
    {
        $venue = $match->provisional_venue_id ? Venue::query()->find($match->provisional_venue_id) : null;
        $court = $match->provisional_court_id ? $venue?->courts()->whereKey($match->provisional_court_id)->first() : null;

        return [
            'date' => $match->provisional_date,
            'time' => $match->provisional_time,
            'duration_minutes' => $match->provisional_duration_minutes ?? $match->duration_minutes,
            'venue_id' => $match->provisional_venue_id,
            'court_id' => $match->provisional_court_id,
            'second_court_id' => $match->provisional_second_court_id,
            'original_date' => $match->provisional_date,
            'original_time' => $match->provisional_time,
            'original_venue' => $venue?->name,
            'original_court' => $court?->name,
        ] + $this->cleared();
    }

    /**
     * The proposals that occupy a court on this day, as matches at their
     * provisional place. They are not saved; the conflict check and the
     * timetable only count with them.
     *
     * @return Collection<int, FixtureMatch>
     */
    public function onDate(int $versionId, string $date): Collection
    {
        return FixtureMatch::query()
            ->where('version_id', $versionId)
            ->whereDate('provisional_date', $date)
            ->get()
            ->map(fn (FixtureMatch $match): FixtureMatch => $this->atProvisionalPlace($match));
    }

    /** An unsaved copy of the match with its placement replaced by the proposal. */
    public function atProvisionalPlace(FixtureMatch $match): FixtureMatch
    {
        $copy = clone $match;
        $copy->setRawAttributes(array_merge($match->getAttributes(), collect(self::FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $match->getAttributes()['provisional_'.$field] ?? null])
            ->all()));
        $copy->duration_minutes ??= $match->duration_minutes;
        $copy->setRelations([]);

        return $copy;
    }

    /**
     * A request from before this way of working was already at the new place.
     * That place becomes the proposal and the match goes back to the
     * baseline, the last place Nevobo and the club agreed on.
     */
    public function convertToProposal(FixtureMatch $match): bool
    {
        if ($match->provisional_date !== null || $match->original_date === null || $match->date === null
            || $match->original_date->toDateString() === $match->date->toDateString()) {
            return false;
        }

        $venue = $this->venueForName($match->original_venue);
        $venueId = $venue?->id ?? $match->venue_id;
        $court = $venue && $match->original_court
            ? $venue->courts()->where('name', $match->original_court)->first()
            : null;
        $courtId = $court?->id ?? ($venueId === $match->venue_id ? $match->court_id : null);

        $match->update($this->columns($match->only(self::FIELDS)) + [
            'date' => $match->original_date,
            'time' => $match->original_time ?? $match->time,
            'venue_id' => $venueId,
            'court_id' => $courtId,
            'second_court_id' => $courtId === $match->court_id ? $match->second_court_id : null,
        ]);

        return true;
    }

    /** @return Collection<int, FixtureMatch> the current club's requests that are still at the new place */
    public function toConvert(): Collection
    {
        return FixtureMatch::query()
            ->whereIn('version_id', ScheduleVersion::query()
                ->whereIn('status', [ScheduleVersionStatus::Draft, ScheduleVersionStatus::Published])->select('id'))
            ->where(fn ($query) => $query->where('change_status', ChangeStatus::Requested)
                ->orWhere('change_requested', true))
            ->whereNull('provisional_date')
            ->get();
    }

    /** Finds the current club's venue by name, also through an alias or spelling variant Nevobo uses. */
    public function venueForName(?string $name): ?Venue
    {
        if (!filled($name)) {
            return null;
        }

        // The old RSS parser sometimes stored the address after the venue
        // name. So both the full historic value and the part before the first
        // comma are tried.
        $variants = collect([$name, Str::before($name, ',')])
            ->map(fn (string $variant): string => trim($variant))
            ->filter()
            ->unique();

        return Venue::query()
            ->with('aliases')
            ->get()
            ->first(fn (Venue $venue): bool => $variants->contains(fn (string $variant): bool => VenueName::equals($venue->name, $variant)
                || VenueName::equals($venue->venue_code, $variant)
                || $venue->aliases->contains(fn (VenueAlias $alias): bool => VenueName::equals($alias->name, $variant))));
    }
}
