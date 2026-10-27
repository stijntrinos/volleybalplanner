<?php

namespace App\Support\Demo;

use App\Models\Club;
use App\Planner\Enums\MatchType;
use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Services\Nevobo\NevoboClubLinker;
use App\Planner\Services\NevoboRssService;
use App\Planner\Services\RssPlanningImportService;
use App\Planner\Support\ClubTeamMatcher;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Fills a demo club with the real teams and programme of a Nevobo club, so a
 * prospect sees their own season in the planner: teams and feeds via the
 * NevoboClubLinker, the halls the club plays its home matches in (the sync
 * never creates venues itself) and a draft version from the feeds.
 */
final class NevoboDemoFiller
{
    /** Home venues beyond these few are rare and would only clutter the demo. */
    private const MAX_VENUES = 3;

    private const MAX_COURTS = 6;

    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly NevoboClubLinker $linker,
        private readonly NevoboRssService $rss,
        private readonly RssPlanningImportService $import,
    ) {}

    /**
     * @param  list<string>  $nevoboTeamIds  ids from NevoboClubDirectory::teams()
     * @return array{teams: int, venues: int, matches: int, error: string|null} error when the programme could not be imported
     *
     * @throws NevoboUnavailable when the teams could not be linked
     */
    public function fill(Club $club, string $clubCode, array $nevoboTeamIds): array
    {
        $linked = $this->linker->link($club, $clubCode, $nevoboTeamIds);

        return $this->currentClub->run($club, function () use ($linked): array {
            PlannerSetting::current()->update(DemoClubBuilder::settings());
            $venues = $this->createHomeVenues($this->feedItems());

            try {
                $result = $this->import->sync();
            } catch (RuntimeException $exception) {
                return ['teams' => $linked['created'], 'venues' => $venues, 'matches' => 0, 'error' => $exception->getMessage()];
            }

            $result['version']->update(['name' => __('Nevobo-programma')]);
            $this->spreadOverCourts($result['version']->matches()->where('match_type', MatchType::Home)->get());

            return ['teams' => $linked['created'], 'venues' => $venues, 'matches' => $result['added'], 'error' => null];
        });
    }

    /** @return list<array<string, mixed>> */
    private function feedItems(): array
    {
        $items = [];
        foreach (TeamSource::query()->forImport()->get() as $source) {
            $result = $this->rss->fetch($source);
            foreach ($result['items'] ?? [] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * The locations of the club's own home matches become its venues, the
     * busiest one the main hall with duties. Courts: as many as matches
     * played there at the same moment. Each team plays at its usual hall.
     *
     * @param  list<array<string, mixed>>  $items
     * @return int the number of venues created
     */
    private function createHomeVenues(array $items): int
    {
        $matcher = ClubTeamMatcher::forCurrentClub();
        $home = collect($items)
            ->filter(fn (array $item): bool => filled($item['location']) && !($item['cancelled'] ?? false) && $matcher->isOwnTeam($item['home_team']))
            ->unique(fn (array $item): string => $item['code'] ?? $item['date'].$item['time'].$item['home_team']);

        $locations = $home->groupBy('location')
            ->sortByDesc(fn (Collection $matches): int => $matches->count())
            ->take(self::MAX_VENUES);

        $venues = [];
        foreach ($locations as $location => $matches) {
            $isMain = $venues === [];
            $venue = Venue::query()->create([
                'name' => $location,
                'is_managed' => $isMain,
                'has_duties' => $isMain,
                'for_matches' => true,
                'for_trainings' => $isMain,
                'is_active' => true,
            ]);
            $courts = min(self::MAX_COURTS, max(1, $matches->countBy(fn (array $item): string => $item['date'].' '.$item['time'])->max()));
            foreach (range(1, $courts) as $number) {
                Court::query()->create(['venue_id' => $venue->id, 'name' => (string) $number, 'sort_order' => $number, 'is_active' => true]);
            }
            $venues[$location] = $venue;

            if ($isMain) {
                PlannerSetting::current()->update(['main_venue_id' => $venue->id]);
            }
        }

        foreach (Team::query()->get() as $team) {
            $location = $home->filter(fn (array $item): bool => $matcher->isOwnTeamWithCode($item['home_team'], $team->code))
                ->countBy('location')->sortDesc()->keys()->first();
            if ($location !== null && isset($venues[$location])) {
                $team->update(['default_venue_id' => $venues[$location]->id]);
            }
        }

        return count($venues);
    }

    /**
     * The sync puts every home match on the first court; matches at the same
     * moment in the same hall go side by side instead.
     *
     * @param  Collection<int, FixtureMatch>  $matches
     */
    private function spreadOverCourts(Collection $matches): void
    {
        $courts = Court::query()->where('is_training_court', false)->orderBy('sort_order')->get()->groupBy('venue_id');

        $matches->filter(fn (FixtureMatch $match): bool => $match->venue_id !== null)
            ->groupBy(fn (FixtureMatch $match): string => $match->venue_id.' '.$match->date->toDateString().' '.$match->time)
            ->each(function (Collection $sameMoment) use ($courts): void {
                $venueCourts = $courts->get($sameMoment->first()->venue_id, collect())->values();
                foreach ($sameMoment->values() as $index => $match) {
                    $court = $venueCourts->get($index);
                    if ($court !== null) {
                        $match->update(['court_id' => $court->id, 'second_court_id' => null, 'original_court' => $court->name]);
                    }
                }
            });
    }
}
