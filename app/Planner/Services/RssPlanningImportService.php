<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\NevoboChangeStatus;
use App\Planner\Enums\NevoboStatus;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\Court;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Models\Venue;
use App\Planner\Services\Import\RssMatchIndex;
use App\Planner\Services\Import\RssVenues;
use App\Planner\Support\ClubTeamMatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brings the current club's draft planning in line with the Nevobo RSS feeds
 * of its teams, without ever overwriting manual planning: changes to a match
 * someone planned by hand are parked for review (see processNevoboSchedule()).
 */
class RssPlanningImportService
{
    public function __construct(
        private readonly NevoboRssService $rss,
        private readonly ImportService $import,
        private readonly SittingVolleyballService $sittingVolleyball,
        private readonly ProvisionalMove $provisionalMove,
        private readonly RssVenues $venues,
    ) {}

    /**
     * @return array{version: ScheduleVersion, added: int, updated: int, applied: int, conflicts: int, skipped: int, sources: int, errors: list<string>}
     */
    public function sync(?ScheduleVersion $version = null, ?int $userId = null): array
    {
        if ($version && !$version->isDraft()) {
            $version = null;
        }

        // Schedule feeds only: a standings feed holds no matches and would be
        // read here as an empty programme.
        $sources = TeamSource::query()->forImport()->with('team')->get();
        if ($sources->isEmpty()) {
            throw new RuntimeException(__('Er zijn geen actieve team-RSS-bronnen geconfigureerd.'));
        }

        $feeds = [];
        $errors = [];
        foreach ($sources as $source) {
            $result = $this->rss->fetch($source, true);
            if (!$result['ok']) {
                $errors[] = "{$source->team->code} · {$source->name}: {$result['reason']}";

                continue;
            }
            foreach ($result['items'] as $item) {
                $feeds[] = $item;
            }
        }
        if ($feeds === []) {
            throw new RuntimeException(__('Geen enkele RSS-bron kon wedstrijden opleveren.').($errors ? ' '.implode(' | ', $errors) : ''));
        }

        return DB::transaction(fn (): array => $this->process($feeds, $errors, $version, $userId));
    }

    /**
     * @param  list<array<string, mixed>>  $feeds
     * @param  list<string>  $errors
     * @return array{version: ScheduleVersion, added: int, updated: int, applied: int, conflicts: int, skipped: int, sources: int, errors: list<string>}
     */
    private function process(array $feeds, array $errors, ?ScheduleVersion $version, ?int $userId): array
    {
        $version ??= ScheduleVersion::create([
            'name' => 'Nevobo RSS '.now()->format('d-m-Y H:i'),
            'status' => ScheduleVersionStatus::Draft,
            'created_by' => $userId,
        ]);
        $counts = ['added' => 0, 'updated' => 0, 'applied' => 0, 'conflicts' => 0, 'skipped' => 0];
        $newIds = [];
        $index = new RssMatchIndex($version);
        $teams = Team::query()->get();
        $matcher = ClubTeamMatcher::forCurrentClub();
        $this->venues->reset();

        foreach ($feeds as $item) {
            // The sources are pool feeds, so a feed holds the whole programme
            // of the pool, including matches between two other clubs. Those
            // do not belong in our planning.
            if (!$matcher->isOwnTeam($item['home_team']) && !$matcher->isOwnTeam($item['away_team'])) {
                $counts['skipped']++;

                continue;
            }

            // A cancelled match is no longer in the planning file but stays in
            // the feed. Creating it made it "removed" at every import, and
            // whoever deleted it saw it back the next morning. What is already
            // there is left to the planner.
            if ($item['cancelled'] ?? false) {
                $counts['skipped']++;

                continue;
            }

            $homeTeam = $this->teamFromName($item['home_team'], $teams, $matcher);
            $awayTeam = $this->teamFromName($item['away_team'], $teams, $matcher);
            $code = $item['code'] ?: RssMatchIndex::syntheticCode($item);
            $venue = $this->venues->venue($item['location']);
            $sitting = $this->sittingVolleyball->classify(
                $item['home_team'], $item['away_team'], $venue, $venue?->for_matches ? MatchType::Home : MatchType::Away,
            );
            $isHome = $sitting['match_type'] === MatchType::Home;
            $court = $venue ? $this->venues->court($venue, $isHome ? $homeTeam : null) : null;
            $existing = $index->find($item, $code);
            $values = [
                'date' => $item['date'], 'time' => $item['time'],
                'duration_minutes' => $isHome ? ($homeTeam?->default_match_duration ?: 120) : 120,
                'home_team_name' => $item['home_team'], 'away_team_name' => $item['away_team'],
                'home_team_id' => $homeTeam?->id,
                'away_team_id' => $awayTeam?->id,
                'pool' => $item['pool'], 'match_code' => $code,
                'match_type' => $sitting['match_type'], 'is_sitting_volleyball' => $sitting['is_sitting_volleyball'],
                'venue_id' => $venue?->id, 'court_id' => $court?->id, 'second_court_id' => null,
                'original_date' => $item['date'], 'original_time' => $item['time'],
                'original_venue' => $item['location'], 'original_court' => $court?->name,
                'created_by' => $userId,
            ];

            if (!$existing) {
                $new = $version->matches()->create($values);
                $index->add($new, $item, $code);
                $newIds[] = $new->id;
                $counts['added']++;

                continue;
            }

            // Names, opponent and pool are descriptive and may always follow
            // the federation. Date, time, venue and court are planning choices
            // and are weighed separately.
            $existing->update(Arr::except($values, [
                'date', 'time', 'duration_minutes', 'venue_id', 'court_id', 'second_court_id',
                'original_date', 'original_time', 'original_venue', 'original_court',
                'created_by',
                ...$this->untouchable($existing, $item, $venue),
            ]));
            $counts['updated']++;

            match ($this->processNevoboSchedule($existing, $item, $venue, $court)) {
                NevoboChangeStatus::Applied => $counts['applied']++,
                NevoboChangeStatus::Conflict => $counts['conflicts']++,
                default => null,
            };
        }
        $this->import->applyTeamDefaults($version);
        // Automatically applied team defaults are the starting point and must
        // not be marked red as a manual change. Only for matches of this run:
        // existing matches have a reference point that keeps manual planning
        // visible.
        $version->matches()->whereKey($newIds)->with(['venue', 'court'])->get()
            ->each(fn (FixtureMatch $match) => $match->update([
                'original_date' => $match->date,
                'original_time' => $match->time,
                'original_venue' => $match->venue?->name,
                'original_court' => $match->court?->name,
            ]));

        return ['version' => $version] + $counts + ['sources' => count($feeds), 'errors' => $errors];
    }

    /**
     * Decides what a fresh Nevobo schedule means for a match we already hold.
     *
     * The original_* columns are the last schedule Nevobo gave us. If the feed
     * still matches those, Nevobo changed nothing and the planning is left
     * alone. If it differs, the match is only rescheduled automatically when
     * nobody has planned it by hand; otherwise the new values are parked for
     * review so a night job can never discard someone's work.
     *
     * @param  array<string, mixed>  $item
     */
    private function processNevoboSchedule(FixtureMatch $match, array $item, ?Venue $venue, ?Court $court): NevoboChangeStatus
    {
        $match->loadMissing(['venue', 'court']);

        $dateDiffers = $match->original_date?->toDateString() !== $item['date'];
        $timeDiffers = FixtureMatch::shortTime($match->original_time) !== FixtureMatch::shortTime($item['time']);
        $venueDiffers = !$this->venues->isSameVenue($match->original_venue, $item['location'], $venue);

        if (!$dateDiffers && !$timeDiffers && !$venueDiffers) {
            // Clear false notices of older, team-name based syncs as soon as
            // the match code confirms source and reference point are equal.
            if (in_array($match->nevobo_change_status, NevoboChangeStatus::open(), true)) {
                $match->update($this->clearedNevoboNotice());
            }

            return NevoboChangeStatus::None;
        }

        // A venue change needs no planning choice: take it over directly and
        // do not leave it on the dashboard for review. An unknown venue stays
        // up for review, since it cannot safely be put in the planning.
        if (!$dateDiffers && !$timeDiffers && $venueDiffers && $venue) {
            $match->update([
                'venue_id' => $venue->id,
                'court_id' => $court?->id,
                'second_court_id' => null,
                'original_venue' => $item['location'],
                'original_court' => $court?->name,
            ] + $this->clearedNevoboNotice());

            return NevoboChangeStatus::None;
        }

        $nevoboValues = [
            'nevobo_changed_at' => now(),
            // Also keep the previous federation moment. When applying
            // automatically original_* moves along at once, and the dashboard
            // would only show the new value: exactly what changed was unclear.
            'nevobo_previous_date' => $match->original_date,
            'nevobo_previous_time' => $match->original_time,
            'nevobo_previous_venue' => $match->original_venue,
            'nevobo_new_date' => $item['date'],
            'nevobo_new_time' => $item['time'],
            'nevobo_new_venue' => $item['location'],
        ];

        // A move awaiting approval: the feed tells whether it came. If the
        // match is there on the proposed day and time it has been processed
        // and moves to the new place. Anything else is a choice for the
        // planner, which the night must not make.
        if ($match->hasProvisionalPlacement()) {
            $confirmed = $match->provisional_date->toDateString() === $item['date']
                && FixtureMatch::shortTime($match->provisional_time) === FixtureMatch::shortTime($item['time']);
            if (!$confirmed) {
                $match->update($nevoboValues + ['nevobo_change_status' => NevoboChangeStatus::Conflict]);

                return NevoboChangeStatus::Conflict;
            }

            $match->update($nevoboValues + $this->provisionalMove->finalize($match) + [
                'original_venue' => $item['location'] ?? $match->original_venue,
                'nevobo_change_status' => NevoboChangeStatus::Applied,
                'nevobo_status' => NevoboStatus::Applied,
                'change_status' => null,
                'change_requested' => false,
                'change_requested_at' => null,
                'change_requested_by' => null,
            ]);

            return NevoboChangeStatus::Applied;
        }

        if ($match->deviatesFromNevobo()) {
            $match->update($nevoboValues + ['nevobo_change_status' => NevoboChangeStatus::Conflict]);

            return NevoboChangeStatus::Conflict;
        }

        $match->update($nevoboValues + [
            'nevobo_change_status' => NevoboChangeStatus::Applied,
            'date' => $item['date'],
            'time' => $item['time'],
            'venue_id' => $venue?->id ?? $match->venue_id,
            'court_id' => $court?->id ?? $match->court_id,
            'original_date' => $item['date'],
            'original_time' => $item['time'],
            'original_venue' => $item['location'],
            'original_court' => $court?->name ?? $match->court?->name,
        ]);

        return NevoboChangeStatus::Applied;
    }

    /**
     * The fields a feed leaves alone on an existing match.
     *
     * A feed without a match code knows less than what is already there. Its
     * synthetic code must not replace the federation code, and a location
     * that yields no venue of ours says nothing about a match we already have
     * in one of our venues; otherwise every home match from such a feed became
     * an away match.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function untouchable(FixtureMatch $existing, array $item, ?Venue $venue): array
    {
        $fields = [];
        if (!$item['code'] && filled($existing->match_code) && !RssMatchIndex::isSynthetic($existing->match_code)) {
            $fields[] = 'match_code';
        }
        if (!$venue && $existing->venue_id) {
            $fields[] = 'match_type';
            $fields[] = 'is_sitting_volleyball';
        }

        return $fields;
    }

    /**
     * The team from the match name, not the team of the source: a pool feed
     * of one team also lists the matches of its sibling teams.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function teamFromName(string $name, Collection $teams, ClubTeamMatcher $matcher): ?Team
    {
        if (!$matcher->isOwnTeam($name)) {
            return null;
        }

        return $teams->first(fn (Team $team): bool => $matcher->isOwnTeamWithCode($name, $team->code));
    }

    /** @return array<string, mixed> */
    private function clearedNevoboNotice(): array
    {
        return [
            'nevobo_change_status' => NevoboChangeStatus::None,
            'nevobo_changed_at' => null,
            'nevobo_previous_date' => null,
            'nevobo_previous_time' => null,
            'nevobo_previous_venue' => null,
            'nevobo_new_date' => null,
            'nevobo_new_time' => null,
            'nevobo_new_venue' => null,
        ];
    }
}
