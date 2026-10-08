<?php

namespace App\Planner\Livewire;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Livewire\Concerns\ResolvesPublicClub;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Planner\Models\VolleystarsTask;
use App\Planner\Support\DateFormat;
use App\Planner\Support\SearchFilter;
use App\Planner\Support\VolleystarsDuty;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Public overview of matches and duties from the published planning. A whole
 * season is over three hundred matches on almost sixty match days; those used
 * to be listed one below the other. That is why this page shows one month at
 * a time, with a team and venue filter above it.
 *
 * Hall duties and referee/scorer only show with the duties module, VolleyStars
 * only with the volleystars module.
 *
 * @phpstan-type Planning array{matches: Collection<int, FixtureMatch>, hall_duties: Collection<int, HallDuty>, volleystars: Collection<int, VolleystarsDuty>}
 * @phpstan-type VenueGroup array{name: ?string, city: ?string, matches: Collection<int, FixtureMatch>, hall_duties: Collection<int, HallDuty>, volleystars_tasks: Collection<int, VolleystarsDuty>}
 */
class MatchOverview extends Component
{
    use ResolvesPublicClub;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    #[Url(as: 'team', except: '')]
    public string $team = '';

    #[Url(as: 'hal', except: '')]
    public string $venue = '';

    /** The visible month as "yyyy-mm". */
    #[Url(as: 'maand', except: '')]
    public string $month = '';

    private ?ScheduleVersion $versionCache = null;

    private bool $versionLoaded = false;

    /** @var Planning|null */
    private ?array $seasonCache = null;

    /** @var Planning|null */
    private ?array $filteredCache = null;

    public function mount(): void
    {
        $this->chooseValidMonth();
    }

    public function updatedSearch(): void
    {
        $this->chooseValidMonth();
    }

    public function updatedTeam(): void
    {
        $this->chooseValidMonth();
    }

    public function updatedVenue(): void
    {
        $this->chooseValidMonth();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->team = '';
        $this->venue = '';
        $this->chooseValidMonth();
    }

    public function render(): View
    {
        $filtered = $this->filtered();

        return view('planner::livewire.match-overview', [
            'days' => $this->daysOfMonth($filtered),
            'months' => $this->months($filtered),
            'teams' => $this->teams(),
            'venues' => $this->venues(),
            'version' => $this->version(),
            'matchCount' => $filtered['matches']->count(),
            'hallDutyCount' => $filtered['hall_duties']->count(),
            'volleystarsCount' => $filtered['volleystars']->count(),
            'hasFilter' => $this->hasFilter(),
            'showDuties' => $this->dutiesEnabled(),
            'showVolleystars' => $this->volleystarsEnabled(),
        ])->layout('planner::layouts.public', [
            'title' => __('Wedstrijden & diensten'),
            'description' => __('Bekijk alle komende thuiswedstrijden, scheidsrechters, tellers en zaaldiensten uit de actuele planning.'),
        ]);
    }

    private function hasFilter(): bool
    {
        return $this->search !== '' || $this->team !== '' || $this->venue !== '';
    }

    private function dutiesEnabled(): bool
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Duties);
    }

    private function volleystarsEnabled(): bool
    {
        return app(CurrentClub::class)->require()->hasFeature(PlannerFeature::Volleystars);
    }

    /**
     * Moves the visible month to the first month with results as soon as a
     * filter emptied the current one. Otherwise you look at an empty page
     * while the hit is a month further on.
     */
    private function chooseValidMonth(): void
    {
        $this->filteredCache = null;
        $available = $this->months($this->filtered())->pluck('value');

        if ($available->isNotEmpty() && !$available->contains($this->month)) {
            $this->month = (string) $available->first();
        }
    }

    /** Only the published planning is public; drafts never show here. */
    private function version(): ?ScheduleVersion
    {
        if (!$this->versionLoaded) {
            $this->versionLoaded = true;
            $this->versionCache = ScheduleVersion::query()
                ->where('status', ScheduleVersionStatus::Published)
                ->latest('id')
                ->first();
        }

        return $this->versionCache;
    }

    /**
     * The whole remaining season at once. Three hundred rows is little work
     * for the database, and filtering in PHP keeps ignoring accents and
     * spaces in one place.
     *
     * @return Planning
     */
    private function season(): array
    {
        return $this->seasonCache ??= $this->loadSeason();
    }

    /** @return Planning */
    private function loadSeason(): array
    {
        $version = $this->version();

        if (!$version) {
            return ['matches' => collect(), 'hall_duties' => collect(), 'volleystars' => collect()];
        }

        $matches = $version->matches()
            ->where('match_type', MatchType::Home)
            ->whereNotNull('date')
            ->whereDate('date', '>=', today())
            ->leftJoin('planner_venues as overview_venues', 'planner_matches.venue_id', '=', 'overview_venues.id')
            ->leftJoin('planner_courts as overview_courts', 'planner_matches.court_id', '=', 'overview_courts.id')
            ->select('planner_matches.*')
            ->with(['venue', 'court', 'secondCourt'])
            ->orderBy('planner_matches.date')
            ->orderBy('planner_matches.time')
            ->orderBy('overview_venues.name')
            ->orderBy('overview_courts.sort_order')
            ->orderBy('overview_courts.name')
            ->orderBy('planner_matches.home_team_name')
            ->orderBy('planner_matches.id')
            ->get();

        $hallDuties = $this->dutiesEnabled()
            ? $version->hallDuties()
                ->whereDate('date', '>=', today())
                ->with(['team', 'venue'])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
            : collect();

        $volleystars = $this->volleystarsEnabled()
            ? $version->volleystarsBlocks()
                ->whereDate('date', '>=', today())
                ->with(['venue', 'tasks.team', 'tasks.court'])
                ->orderBy('date')
                ->get()
                ->flatMap(fn (VolleystarsBlock $block): array => $block->tasks
                    ->sortBy('sort_order')
                    ->map(fn (VolleystarsTask $task): VolleystarsDuty => VolleystarsDuty::fromBlock($block, $task))
                    ->values()
                    ->all())
                ->values()
            : collect();

        return ['matches' => $matches, 'hall_duties' => $hallDuties, 'volleystars' => $volleystars];
    }

    /**
     * The season with search term, team and venue applied, but not yet the
     * month: the month picker has to know where the hits are.
     *
     * @return Planning
     */
    private function filtered(): array
    {
        if ($this->filteredCache !== null) {
            return $this->filteredCache;
        }

        ['matches' => $matches, 'hall_duties' => $hallDuties, 'volleystars' => $volleystars] = $this->season();

        if ($this->venue !== '') {
            $venueId = (int) $this->venue;
            $matches = $matches->where('venue_id', $venueId)->values();
            $hallDuties = $hallDuties->where('venue_id', $venueId)->values();
            $volleystars = $volleystars->where('venueId', $venueId)->values();
        }

        if ($this->team !== '') {
            $showDuties = $this->dutiesEnabled();
            $matches = $matches->filter(
                fn (FixtureMatch $match): bool => $match->home_team_name === $this->team
                    || ($showDuties && SearchFilter::isSameTeam($this->team, $match->referee))
                    || ($showDuties && SearchFilter::isSameTeam($this->team, $match->scorer)),
            )->values();
            $hallDuties = $hallDuties->filter(
                fn (HallDuty $duty): bool => SearchFilter::isSameTeam($this->team, $duty->team->code),
            )->values();
            $volleystars = $volleystars->filter(
                fn (VolleystarsDuty $duty): bool => SearchFilter::isSameTeam($this->team, $duty->team),
            )->values();
        }

        if ($this->search !== '') {
            $matches = $matches->filter(
                fn (FixtureMatch $match): bool => SearchFilter::contains($this->matchSearchText($match), $this->search),
            )->values();
            $hallDuties = $hallDuties->filter(
                fn (HallDuty $duty): bool => SearchFilter::contains(collect([
                    $duty->team->code,
                    $duty->team->name,
                    $duty->venue->name,
                    $duty->venue->city,
                ])->filter()->implode(' '), $this->search),
            )->values();
            $volleystars = $volleystars->filter(
                fn (VolleystarsDuty $duty): bool => SearchFilter::contains($duty->searchText(), $this->search),
            )->values();
        }

        return $this->filteredCache = [
            'matches' => $matches,
            'hall_duties' => $hallDuties,
            'volleystars' => $volleystars,
        ];
    }

    /**
     * The months that still have something to show, with their match count.
     *
     * @param  Planning  $filtered
     * @return Collection<int, array{value: string, label: string, count: int}>
     */
    private function months(array $filtered): Collection
    {
        $perMonth = $filtered['matches']
            ->groupBy(fn (FixtureMatch $match): string => $match->date->format('Y-m'));

        return collect([
            ...$perMonth->keys()->all(),
            ...$filtered['hall_duties']->map(fn (HallDuty $duty): string => $duty->date->format('Y-m'))->all(),
            ...$filtered['volleystars']->map(fn (VolleystarsDuty $duty): string => $duty->month())->all(),
        ])
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $month): array => [
                'value' => $month,
                'label' => (string) DateFormat::month($month.'-01'),
                'count' => $perMonth->get($month, collect())->count(),
            ]);
    }

    /**
     * The match days of the visible month, per day grouped by venue. The venue
     * is shown once above the group instead of on every line.
     *
     * @param  Planning  $filtered
     * @return Collection<int, array{date: string, venues: Collection<int, VenueGroup>, match_count: int}>
     */
    private function daysOfMonth(array $filtered): Collection
    {
        $month = $this->month;

        $matchesPerDay = $filtered['matches']
            ->filter(fn (FixtureMatch $match): bool => $match->date->format('Y-m') === $month)
            ->groupBy(fn (FixtureMatch $match): string => $match->date->toDateString());
        $dutiesPerDay = $filtered['hall_duties']
            ->filter(fn (HallDuty $duty): bool => $duty->date->format('Y-m') === $month)
            ->groupBy(fn (HallDuty $duty): string => $duty->date->toDateString());
        $volleystarsPerDay = $filtered['volleystars']
            ->filter(fn (VolleystarsDuty $duty): bool => $duty->month() === $month)
            ->groupBy(fn (VolleystarsDuty $duty): string => $duty->date);

        return collect([
            ...$matchesPerDay->keys()->all(),
            ...$dutiesPerDay->keys()->all(),
            ...$volleystarsPerDay->keys()->all(),
        ])
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $date) use ($matchesPerDay, $dutiesPerDay, $volleystarsPerDay): array {
                $matches = $matchesPerDay->get($date, collect());

                return [
                    'date' => $date,
                    'venues' => $this->venuesOfDay(
                        $matches,
                        $dutiesPerDay->get($date, collect()),
                        $volleystarsPerDay->get($date, collect()),
                    ),
                    'match_count' => $matches->count(),
                ];
            });
    }

    /**
     * Everything of one day per venue: hall duties and VolleyStars belong to
     * their own venue, otherwise they get mixed up on a day with two venues.
     * The lists arrive sorted by time, so the venue with the earliest match
     * comes first; a venue with only duties joins at the end.
     *
     * @param  Collection<int, FixtureMatch>  $matches
     * @param  Collection<int, HallDuty>  $hallDuties
     * @param  Collection<int, VolleystarsDuty>  $volleystars
     * @return Collection<int, VenueGroup>
     */
    private function venuesOfDay(Collection $matches, Collection $hallDuties, Collection $volleystars): Collection
    {
        $venues = [];
        foreach ($matches as $match) {
            $venues[(string) $match->venue_id] ??= ['name' => $match->venue?->name, 'city' => $match->venue?->city];
        }

        foreach ($hallDuties as $duty) {
            $venues[(string) $duty->venue_id] ??= ['name' => $duty->venue->name, 'city' => $duty->venue->city];
        }

        foreach ($volleystars as $task) {
            $venues[(string) $task->venueId] ??= ['name' => $task->venue, 'city' => null];
        }

        return collect($venues)
            ->map(fn (array $venue, int|string $key): array => [
                'name' => $venue['name'],
                'city' => $venue['city'],
                'matches' => $matches->filter(fn (FixtureMatch $match): bool => (string) $match->venue_id === (string) $key)->values(),
                'hall_duties' => $hallDuties->filter(fn (HallDuty $duty): bool => (string) $duty->venue_id === (string) $key)->values(),
                'volleystars_tasks' => $volleystars->filter(fn (VolleystarsDuty $task): bool => (string) $task->venueId === (string) $key)->values(),
            ])
            ->values();
    }

    /** @return Collection<int, string> */
    private function teams(): Collection
    {
        return $this->season()['matches']
            ->pluck('home_team_name')
            ->filter()
            ->unique()
            ->sortBy(fn (string $team): string => $this->naturalKey($team), SORT_NATURAL)
            ->values();
    }

    /** @return Collection<int, Venue> */
    private function venues(): Collection
    {
        return $this->season()['matches']
            ->pluck('venue')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /** Puts "DS 10" after "DS 2" by padding the number. */
    private function naturalKey(string $team): string
    {
        return (string) preg_replace_callback(
            '/\d+/',
            fn (array $hit): string => str_pad($hit[0], 4, '0', STR_PAD_LEFT),
            $team,
        );
    }

    private function matchSearchText(FixtureMatch $match): string
    {
        $showDuties = $this->dutiesEnabled();

        return collect([
            $match->home_team_name,
            $match->away_team_name,
            $match->match_code,
            $match->pool,
            $showDuties ? $match->referee : null,
            $showDuties ? $match->scorer : null,
            $match->venue?->name,
            $match->venue?->city,
            $match->court?->name,
            $match->secondCourt?->name,
            $match->secondCourt ? 'CC CenterCourt' : null,
            // No notes: they are internal, and a match on them would reveal them.
        ])->filter()->implode(' ');
    }
}
