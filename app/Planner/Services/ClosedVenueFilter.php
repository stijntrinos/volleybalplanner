<?php

namespace App\Planner\Services;

use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Models\Availability;
use App\Planner\Models\Training;
use Illuminate\Support\Collection;

/**
 * A closed venue has no trainings either. Trainings are fixed per weekday, so
 * during a holiday or on a closing day they still showed up in the planner
 * while nobody trains there.
 *
 * Per venue the rule that weighs most for that day counts, as in the venue
 * occupancy: a date rule "available" lifts a closed period for that one day.
 * A closure with times only removes the trainings that fall within that window.
 */
class ClosedVenueFilter
{
    public function __construct(private readonly ConflictEngine $conflicts) {}

    /**
     * @param  Collection<int, Training>  $trainings
     * @param  Collection<int, Availability>  $rules
     * @return Collection<int, Training>
     */
    public function trainingsInOpenVenues(Collection $trainings, Collection $rules, string $date): Collection
    {
        $closures = $trainings->pluck('venue_id')->filter()->unique()
            ->mapWithKeys(fn (int $venueId): array => [$venueId => $this->applicableRule($rules, $venueId, $date)])
            ->filter(fn (?Availability $rule): bool => $rule?->status === AvailabilityStatus::Closed);

        if ($closures->isEmpty()) {
            return $trainings;
        }

        return $trainings->reject(function (Training $training) use ($closures): bool {
            $closure = $closures->get($training->venue_id);
            if (!$closure) {
                return false;
            }

            return !$closure->hasTimeWindow() || $this->overlaps($training, $closure);
        })->values();
    }

    /** @param Collection<int, Availability> $rules */
    private function applicableRule(Collection $rules, int $venueId, string $date): ?Availability
    {
        return $rules
            ->filter(fn (Availability $rule): bool => $rule->venue_id === $venueId
                && $rule->court_id === null
                && $this->conflicts->ruleAppliesOn($rule, $date))
            ->sortByDesc(fn (Availability $rule): int => $this->conflicts->scopeWeight($rule))
            ->first();
    }

    /** A training without times cannot be compared and stays. */
    private function overlaps(Training $training, Availability $closure): bool
    {
        if (blank($training->start_time) || blank($training->end_time)) {
            return false;
        }

        $time = fn (?string $value): string => substr((string) $value, 0, 5);

        return $time($training->start_time) < $time($closure->end_time)
            && $time($training->end_time) > $time($closure->start_time);
    }
}
