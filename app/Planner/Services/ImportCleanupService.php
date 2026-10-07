<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Enums\Source;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\HallDuty;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Training;
use App\Planner\Models\Venue;
use App\Planner\Models\VolleystarsBlock;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Selectively removes imported planning data of the current club. The
 * published version and manually managed locations are always protected.
 */
class ImportCleanupService
{
    public const MATCHES = 'matches';

    public const ACTIVITIES = 'activities';

    public const HALL_DUTIES = 'hall_duties';

    public const VOLLEYSTARS = 'volleystars';

    public const TRAININGS = 'trainings';

    public const VERSIONS = 'versions';

    public const COURTS = 'courts';

    public const VENUES = 'venues';

    public const EXTERNAL_VENUES = 'external_venues';

    /**
     * Categories hanging off a schedule version. The published version is
     * always excluded here, just like manually managed locations.
     *
     * @var array<string, class-string>
     */
    private const VERSION_BOUND = [
        self::MATCHES => FixtureMatch::class,
        self::ACTIVITIES => Activity::class,
        self::HALL_DUTIES => HallDuty::class,
        self::VOLLEYSTARS => VolleystarsBlock::class,
    ];

    /** Modules a category belongs to; without the module it is not offered. */
    private const FEATURES = [
        self::HALL_DUTIES => PlannerFeature::Duties,
        self::VOLLEYSTARS => PlannerFeature::Volleystars,
        self::TRAININGS => PlannerFeature::Trainings,
    ];

    /**
     * The categories the current club can clean up, with their label.
     *
     * @return array<string, string>
     */
    public function categories(): array
    {
        $labels = [
            self::MATCHES => __('Wedstrijden'), self::ACTIVITIES => __('Zaalactiviteiten'),
            self::HALL_DUTIES => __('Zaaldiensten'), self::VOLLEYSTARS => __('VolleyStars-blokken en taken'),
            self::TRAININGS => __('Geïmporteerde trainingen'), self::VERSIONS => __('Planningversies'),
            self::COURTS => __('Ongebruikte importvelden'), self::VENUES => __('Ongebruikte importhallen'),
            self::EXTERNAL_VENUES => __('Externe sporthallen loskoppelen'),
        ];
        $club = app(CurrentClub::class)->require();

        return array_filter(
            $labels,
            fn (string $category): bool => !isset(self::FEATURES[$category]) || $club->hasFeature(self::FEATURES[$category]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        $protected = $this->publishedVersionId();
        $available = $this->categories();

        $counts = [];
        foreach (self::VERSION_BOUND as $category => $model) {
            $counts[$category] = $this->versionBoundQuery($model, $protected)->count();
        }
        $counts += [
            self::TRAININGS => Training::query()->where('source', Source::Import)->count(),
            self::VERSIONS => $this->versionQuery($protected)->count(),
            self::COURTS => Court::query()->where('source', '!=', Source::Manual)->count(),
            self::VENUES => Venue::query()->where('source', '!=', Source::Manual)->count(),
            self::EXTERNAL_VENUES => $this->externalImportVenues()->count(),
        ];

        return array_intersect_key($counts, $available);
    }

    /**
     * Records that go along when "versions" is ticked, even when the user did
     * not select those categories: deleting a version cascades to everything
     * that hangs off it.
     *
     * @return array<string, int>
     */
    public function cascadeCounts(): array
    {
        $versionIds = $this->versionQuery($this->publishedVersionId())->pluck('id');
        if ($versionIds->isEmpty()) {
            return [];
        }

        $cascade = [];
        foreach (self::VERSION_BOUND as $category => $model) {
            $count = $model::query()->whereIn('version_id', $versionIds)->count();
            if ($count > 0) {
                $cascade[$category] = $count;
            }
        }

        return $cascade;
    }

    public function hasPublishedVersion(): bool
    {
        return $this->publishedVersionId() !== null;
    }

    /**
     * @param  list<string>  $categories
     * @return array<string, int> deleted records per category
     */
    public function cleanup(array $categories): array
    {
        $categories = array_values(array_intersect(array_keys($this->categories()), $categories));

        return DB::transaction(function () use ($categories): array {
            $protected = $this->publishedVersionId();
            $deleted = array_fill_keys($categories, 0);

            foreach (self::VERSION_BOUND as $category => $model) {
                if (in_array($category, $categories, true)) {
                    $deleted[$category] = $this->versionBoundQuery($model, $protected)->delete();
                }
            }
            if (in_array(self::TRAININGS, $categories, true)) {
                $deleted[self::TRAININGS] = Training::query()->where('source', Source::Import)->delete();
            }
            if (in_array(self::VERSIONS, $categories, true)) {
                $deleted[self::VERSIONS] = $this->versionQuery($protected)->delete();
            }
            if (in_array(self::EXTERNAL_VENUES, $categories, true)) {
                $deleted[self::EXTERNAL_VENUES] = $this->cleanupExternalVenues()['venues'];
            }
            foreach ([self::COURTS => Court::class, self::VENUES => Venue::class] as $category => $model) {
                if (!in_array($category, $categories, true)) {
                    continue;
                }
                $model::query()->where('source', '!=', Source::Manual)->each(function ($record) use (&$deleted, $category): void {
                    if (($category === self::COURTS && $this->courtIsUsed($record->id))
                        || ($category === self::VENUES && $this->venueIsUsed($record->id))) {
                        return;
                    }
                    try {
                        $record->delete();
                        $deleted[$category]++;
                    } catch (Throwable) {
                        // A location still in use is deliberately kept.
                    }
                });
            }

            activity('planner-import')
                ->withProperties(['categories' => $categories, 'deleted' => $deleted, 'protected_version_id' => $protected])
                ->log('Importgegevens selectief opgeschoond');

            return $deleted;
        });
    }

    /**
     * Detaches away matches from venues the match import created for
     * opponents, then removes those venues and their unused courts.
     *
     * @return array{venues: int, courts: int, matches: int}
     */
    public function cleanupExternalVenues(): array
    {
        return DB::transaction(function (): array {
            $result = ['venues' => 0, 'courts' => 0, 'matches' => 0];

            foreach ($this->externalImportVenues()->get() as $venue) {
                $courtIds = Court::query()->where('venue_id', $venue->id)->pluck('id');
                $matches = FixtureMatch::query()
                    ->where('match_type', MatchType::Away)
                    ->where(function ($query) use ($venue, $courtIds): void {
                        $query->where('venue_id', $venue->id);
                        if ($courtIds->isNotEmpty()) {
                            $query->orWhereIn('court_id', $courtIds)
                                ->orWhereIn('second_court_id', $courtIds)
                                ->orWhereIn('suggested_court_id', $courtIds);
                        }
                    });

                $result['matches'] += $matches->count();
                $matches->toBase()->update([
                    'venue_id' => null,
                    'court_id' => null,
                    'second_court_id' => null,
                    'suggested_court_id' => null,
                ]);

                foreach (Court::query()->whereIn('id', $courtIds)->get() as $court) {
                    if (!$this->courtIsUsed($court->id)) {
                        $court->delete();
                        $result['courts']++;
                    }
                }

                if (!$this->venueIsUsed($venue->id)) {
                    $venue->delete();
                    $result['venues']++;
                }
            }

            return $result;
        });
    }

    private function publishedVersionId(): ?int
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->value('id');
    }

    /** @param  class-string  $model */
    private function versionBoundQuery(string $model, ?int $protected): Builder
    {
        return $model::query()->when($protected, fn (Builder $query) => $query->where('version_id', '!=', $protected));
    }

    private function versionQuery(?int $protected): Builder
    {
        return ScheduleVersion::query()->when($protected, fn (Builder $query) => $query->whereKeyNot($protected));
    }

    /**
     * Venues the match import created that are only used by away matches.
     *
     * @return Builder<Venue>
     */
    private function externalImportVenues(): Builder
    {
        $id = 'planner_venues.id';

        return Venue::query()
            ->where('source', Source::MatchImport)
            ->where('is_managed', false)
            ->where('has_duties', false)
            ->where('for_matches', false)
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('planner_matches')
                ->whereColumn('planner_matches.venue_id', $id)
                ->where('planner_matches.match_type', MatchType::Away->value))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_matches')
                ->whereColumn('planner_matches.venue_id', $id)
                ->where('planner_matches.match_type', '!=', MatchType::Away->value))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_activities')->whereColumn('planner_activities.venue_id', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_hall_duties')->whereColumn('planner_hall_duties.venue_id', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_volleystars_blocks')->whereColumn('planner_volleystars_blocks.venue_id', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_trainings')->whereColumn('planner_trainings.venue_id', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_teams')->whereColumn('planner_teams.default_venue_id', $id))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('planner_availabilities')->whereColumn('planner_availabilities.venue_id', $id));
    }

    private function courtIsUsed(int $courtId): bool
    {
        return DB::table('planner_matches')->where('court_id', $courtId)->orWhere('second_court_id', $courtId)->orWhere('suggested_court_id', $courtId)->exists()
            || DB::table('planner_activities')->where('court_id', $courtId)->exists()
            || DB::table('planner_volleystars_tasks')->where('court_id', $courtId)->exists()
            || DB::table('planner_availabilities')->where('court_id', $courtId)->exists();
    }

    private function venueIsUsed(int $venueId): bool
    {
        return DB::table('planner_courts')->where('venue_id', $venueId)->exists()
            || DB::table('planner_matches')->where('venue_id', $venueId)->exists()
            || DB::table('planner_activities')->where('venue_id', $venueId)->exists()
            || DB::table('planner_hall_duties')->where('venue_id', $venueId)->exists()
            || DB::table('planner_volleystars_blocks')->where('venue_id', $venueId)->exists()
            || DB::table('planner_trainings')->where('venue_id', $venueId)->exists()
            || DB::table('planner_teams')->where('default_venue_id', $venueId)->exists()
            || DB::table('planner_availabilities')->where('venue_id', $venueId)->exists();
    }
}
