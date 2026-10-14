<?php

namespace App\Planner\Services;

use App\Planner\Enums\AvailabilityStatus;
use App\Planner\Enums\ChangeStatus;
use App\Planner\Enums\MatchType;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\ActivityColor;
use App\Planner\Models\Availability;
use App\Planner\Models\CalendarEvent;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\Training;
use App\Planner\Models\UserPreference;
use App\Planner\Models\Venue;
use App\Planner\Support\Weekday;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\ReadOnlyClub;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything the day planner shows for one day of one version. Split off from
 * PlannerService; use PlannerService::dayData() as the entry point.
 */
class PlannerDayData
{
    public function __construct(
        private readonly ConflictEngine $conflicts,
        private readonly ClosedVenueFilter $closedVenues,
        private readonly ChangeRequestService $changeRequests,
    ) {}

    /** @return array<string, mixed> */
    public function build(ScheduleVersion $version, string $date): array
    {
        $club = app(CurrentClub::class)->require();
        $matches = $version->matches()->whereDate('date', $date)
            ->with(['venue', 'court', 'secondCourt', 'homeTeam', 'provisionalVenue', 'provisionalCourt'])->orderBy('time')->get();
        // Matches being moved to this day but still waiting for approval. They
        // show here as a marker and keep their court occupied.
        $proposals = $version->matches()->whereDate('provisional_date', $date)
            ->with(['venue', 'court'])->orderBy('provisional_time')->get();
        $activities = $version->activities()->whereDate('date', $date)->with(['venue', 'court'])->get();
        $hallDuties = $club->hasFeature(PlannerFeature::Duties)
            ? $version->hallDuties()->whereDate('date', $date)->with(['team', 'venue'])->get()
            : collect();
        $volleystars = $club->hasFeature(PlannerFeature::Volleystars)
            ? $version->volleystarsBlocks()->whereDate('date', $date)->with(['venue', 'tasks.team', 'tasks.court'])->get()
            : collect();
        $preference = UserPreference::query()->where('user_id', auth()->id())->first();
        $showTrainings = $club->hasFeature(PlannerFeature::Trainings) && ($preference->show_trainings ?? true);
        $settings = PlannerSetting::current();
        $inTrainingPeriod = (!$settings->training_period_start || $date >= $settings->training_period_start->toDateString())
            && (!$settings->training_period_end || $date <= $settings->training_period_end->toDateString());
        $trainings = $showTrainings && $inTrainingPeriod
            ? Training::query()->where('weekday', Weekday::name($date))->with(['team', 'venue'])->get()
            : collect();
        $availability = Availability::query()->get();
        $trainings = $this->closedVenues->trainingsInOpenVenues($trainings, $availability, $date);
        // By match code and not by id: duplicating a version copies the match
        // rows to new ids, and the marker would disappear.
        $requestCodes = $this->changeRequests->openRequestCodes();
        // The bar shows every venue where something happens that day -- played
        // or trained -- so you can add one. Which of those are open is up to the
        // page; by default only the main venue.
        $playVenueIds = $matches
            ->where('match_type', MatchType::Home)
            ->pluck('venue_id')->filter()->unique();
        // A hall duty or Volleystars block is part of the club's own work that
        // day too. Otherwise a venue with only a Volleystars block dropped out
        // of the bar and the block was nowhere to be seen.
        $dutyVenueIds = $hallDuties->pluck('venue_id')
            ->merge($volleystars->pluck('venue_id'))
            ->merge($proposals->pluck('provisional_venue_id'))
            ->filter()->unique();
        $venueIds = $playVenueIds
            ->merge($trainings->pluck('venue_id')->filter())
            ->merge($dutyVenueIds)
            ->unique();

        // With nothing on that day the planning falls back on the main venue,
        // so the timetable is not empty when you want to schedule a match.
        if ($venueIds->isEmpty() && $settings->main_venue_id) {
            $venueIds = collect([$settings->main_venue_id]);
        }
        $venues = Venue::query()
            ->where('is_active', true)
            ->with(['courts' => fn ($query) => $query->where('is_active', true)])
            ->when(
                $venueIds->isNotEmpty(),
                fn ($query) => $query->whereIn('id', $venueIds),
                fn ($query) => $query->where('for_matches', true),
            )
            ->inPlanningOrder()->get();

        return [
            'date' => $date,
            // A published version, or a club in read-only mode: look, don't touch.
            'editable' => $version->isDraft() && !ReadOnlyClub::active(),
            'matches' => $matches->map(fn (FixtureMatch $match): array => [
                'id' => $match->id, 'code' => $match->match_code, 'time' => FixtureMatch::shortTime($match->time),
                'duration_minutes' => $match->duration_minutes,
                'home_team' => $match->home_team_name, 'away_team' => $match->away_team_name,
                'venue_id' => $match->venue_id, 'court_id' => $match->court_id, 'second_court_id' => $match->second_court_id,
                'venue' => $match->venue?->name, 'court' => $match->court?->name, 'second_court' => $match->secondCourt?->name,
                'referee' => $match->referee, 'scorer' => $match->scorer,
                'change_status' => $match->change_status?->value,
                'change_requested' => (bool) $match->change_requested,
                'nevobo_status' => $match->nevobo_status->value,
                'has_change_request' => in_array($match->match_code, $requestCodes, true),
                'provisional' => $match->hasProvisionalPlacement() ? self::planningLabel(
                    $match->provisional_date->toDateString(), $match->provisional_time,
                    $match->provisionalVenue?->name, $match->provisionalCourt?->name,
                ) : null,
            ])->values()->all(),
            'provisional' => $proposals->map(fn (FixtureMatch $match): array => [
                'id' => $match->id, 'code' => $match->match_code, 'time' => FixtureMatch::shortTime($match->provisional_time),
                'duration_minutes' => $match->provisional_duration_minutes ?? $match->duration_minutes,
                'home_team' => $match->home_team_name, 'away_team' => $match->away_team_name,
                'venue_id' => $match->provisional_venue_id, 'court_id' => $match->provisional_court_id,
                'second_court_id' => $match->provisional_second_court_id,
                'currently_at' => self::planningLabel($match->date?->toDateString(), $match->time, $match->venue?->name, $match->court?->name),
            ])->values()->all(),
            'venues' => $venues->map(fn (Venue $venue): array => [
                'id' => $venue->id,
                'name' => $venue->name,
                'courts' => $venue->courts->map->only(['id', 'name'])->values()->all(),
                'rental_window' => $this->rentalWindow($venue->id, $date, $availability),
                'matches' => $playVenueIds->contains($venue->id)
                    ? $matches->where('match_type', MatchType::Home)->where('venue_id', $venue->id)->count()
                    : 0,
            ])->values()->all(),
            // The venues open by default: the main venue when it is played in,
            // otherwise every venue with a match, and without matches whatever
            // is shown anyway.
            'default_venues' => $this->defaultVenues($venues, $playVenueIds, $dutyVenueIds, $settings->main_venue_id),
            'hall_duties' => $hallDuties->toArray(),
            // On weekdays there is no hall duty, so the column does not belong
            // there either. Hall duties already planned stay visible, otherwise
            // a changed setting would make existing work disappear.
            'show_hall_duty' => $club->hasFeature(PlannerFeature::Duties)
                && ($hallDuties->isNotEmpty() || $settings->hasHallDutyOnDate($date)),
            'activities' => $activities->toArray(),
            'volleystars' => $volleystars->toArray(),
            'trainings' => $trainings->values()->toArray(),
            'availability' => $availability->toArray(),
            'teams' => Team::query()->get(['id', 'code', 'name'])->toArray(),
            'calendar' => CalendarEvent::query()
                ->addSelect([
                    'type_color' => ActivityColor::query()
                        ->select('color')
                        ->whereColumn('planner_activity_colors.club_id', 'planner_calendar_events.club_id')
                        ->whereColumn('planner_activity_colors.type', 'planner_calendar_events.type')
                        ->limit(1),
                ])
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->get()
                ->toArray(),
            'to_plan' => $this->toPlan($version),
        ];
    }

    /**
     * Home matches without a complete placement, or with a change running.
     *
     * @return list<array<string, mixed>>
     */
    private function toPlan(ScheduleVersion $version): array
    {
        return $version->matches()->where('match_type', MatchType::Home)
            ->where(fn ($query) => $query->whereNull('date')
                ->orWhereNull('time')
                ->orWhereNull('venue_id')
                ->orWhereNull('court_id')
                ->orWhereIn('change_status', [ChangeStatus::Requested, ChangeStatus::ToChange])
                ->orWhere('change_requested', true))
            ->with(['venue:id,name', 'court:id,name', 'provisionalVenue:id,name', 'provisionalCourt:id,name'])
            ->get()
            ->map(fn (FixtureMatch $match): array => [
                'id' => $match->id,
                'match_code' => $match->match_code,
                'home_team' => $match->home_team_name,
                'away_team' => $match->away_team_name,
                'change_status' => $match->change_status?->value,
                'change_requested' => (bool) $match->change_requested,
                'to_change' => $match->change_status === ChangeStatus::ToChange,
                'has_proposal' => $match->hasProvisionalPlacement(),
                'change_requested_at' => $match->change_requested_at?->format('d-m-Y H:i'),
                // With a proposal the match still stands at its own place;
                // before that it already stood at the new one and the
                // reference was the old place.
                'original' => $match->hasProvisionalPlacement()
                    ? self::planningLabel($match->date?->toDateString(), $match->time, $match->venue?->name, $match->court?->name)
                    : self::planningLabel(
                        $match->original_date?->toDateString(), $match->original_time, $match->original_venue, $match->original_court,
                    ),
                'proposal' => $match->hasProvisionalPlacement()
                    ? self::planningLabel(
                        $match->provisional_date->toDateString(), $match->provisional_time,
                        $match->provisionalVenue?->name, $match->provisionalCourt?->name,
                    )
                    : self::planningLabel($match->date?->toDateString(), $match->time, $match->venue?->name, $match->court?->name),
            ])
            ->values()
            ->all();
    }

    /**
     * The venues open when a match day is opened. Otherwise the timetable got
     * so wide that the club's own venue got lost between venues where there
     * is only training that day; those are still in the bar.
     *
     * @param  Collection<int, Venue>  $venues
     * @param  Collection<int, int>  $playVenueIds
     * @param  Collection<int, int>  $dutyVenueIds
     * @return list<int>
     */
    private function defaultVenues(Collection $venues, Collection $playVenueIds, Collection $dutyVenueIds, ?int $mainVenueId): array
    {
        // A venue where the club has a duty that day is always open; otherwise
        // you had to look it up in the bar first to see what there is to do.
        $open = fn (array $ids): array => collect($ids)->merge($dutyVenueIds)
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();

        if ($mainVenueId && $playVenueIds->contains($mainVenueId)) {
            return $open([$mainVenueId]);
        }
        if ($playVenueIds->isNotEmpty()) {
            return $open($playVenueIds->all());
        }

        return $open($venues->pluck('id')->all()) ?: $venues->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The venue is rented per day. When an "available" rule with times
     * applies that day, that is the rental window and it belongs above the
     * column -- otherwise the committee does not know how late it may plan.
     *
     * @param  Collection<int, Availability>  $availability
     */
    private function rentalWindow(int $venueId, string $date, Collection $availability): ?string
    {
        $rule = $availability
            ->filter(fn (Availability $rule): bool => $rule->venue_id === $venueId
                && $rule->court_id === null
                && $rule->status === AvailabilityStatus::Available
                && $rule->hasTimeWindow()
                && $this->conflicts->ruleAppliesOn($rule, $date))
            ->sortByDesc(fn (Availability $rule): int => $this->conflicts->scopeWeight($rule))
            ->first();

        return $rule?->window();
    }

    /** "dd-mm-yyyy · HH:MM · venue · veld X", leaving out what is unknown. */
    public static function planningLabel(?string $date, ?string $time, ?string $venue, ?string $court): ?string
    {
        $parts = collect([
            $date ? CarbonImmutable::parse($date)->format('d-m-Y') : null,
            FixtureMatch::shortTime($time),
            $venue,
            $court ? __('veld :court', ['court' => $court]) : null,
        ])->filter()->values();

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }
}
