<?php

namespace App\Planner\Services\Nevobo;

use App\Models\Club;
use App\Planner\Enums\Source;
use App\Planner\Enums\TeamSourceType;
use App\Planner\Exceptions\NevoboUnavailable;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\Team;
use App\Planner\Models\TeamSource;
use App\Planner\Support\TeamCode;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Facades\DB;

/**
 * Sets up a club's teams and their Nevobo feeds from the federation's team
 * list, instead of entering every feed URL by hand. Linking again is safe:
 * teams are found by code and the feeds this linker manages are updated in
 * place, so nothing is duplicated and hand-made feeds are left alone.
 *
 * @phpstan-import-type NevoboTeam from NevoboClubDirectory
 */
class NevoboClubLinker
{
    public function __construct(private readonly NevoboClubDirectory $directory) {}

    /**
     * @param  list<string>  $nevoboTeamIds  ids from NevoboClubDirectory::teams()
     * @return array{created: int, updated: int, sources: int}
     *
     * @throws NevoboUnavailable
     */
    public function link(Club $club, string $clubCode, array $nevoboTeamIds): array
    {
        $code = NevoboClubDirectory::normalizeClubCode($clubCode);
        $wanted = array_flip($nevoboTeamIds);
        // Only ids that really belong to this club count: the list comes from
        // a form and could be tampered with.
        $teams = array_values(array_filter(
            $this->directory->teams($code),
            static fn (array $team): bool => isset($wanted[$team['nevobo_id']]),
        ));
        $nevoboClub = $this->directory->club($code);
        // HTTP before the transaction: no database lock while Nevobo answers.
        $standings = $this->directory->standingsUrls(array_column($teams, 'nevobo_id'));

        return app(CurrentClub::class)->run($club, fn (): array => DB::transaction(
            fn (): array => $this->apply($code, $nevoboClub['name'] ?? null, $teams, $standings),
        ));
    }

    /**
     * @param  list<NevoboTeam>  $teams
     * @param  array<string, string>  $standings
     * @return array{created: int, updated: int, sources: int}
     */
    private function apply(string $clubCode, ?string $clubName, array $teams, array $standings): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'sources' => 0];
        $existing = Team::query()->get()->keyBy(static fn (Team $team): string => TeamCode::key($team->code));

        foreach ($teams as $nevoboTeam) {
            $team = $existing->get(TeamCode::key($nevoboTeam['code']));

            if ($team === null) {
                $team = Team::query()->create([
                    'code' => $nevoboTeam['code'],
                    'name' => $nevoboTeam['name'],
                    'source' => Source::Import,
                ]);
                $existing->put(TeamCode::key($team->code), $team);
                $counts['created']++;
            } else {
                // Everything else on an existing team was set by the club.
                if (trim($team->name) === '') {
                    $team->update(['name' => $nevoboTeam['name']]);
                }
                $counts['updated']++;
            }

            $feeds = [
                [TeamSourceType::Schedule, __('Nevobo-programma'), $nevoboTeam['schedule_url']],
                [TeamSourceType::Results, __('Nevobo-uitslagen'), $nevoboTeam['results_url']],
            ];
            if (isset($standings[$nevoboTeam['nevobo_id']])) {
                $feeds[] = [TeamSourceType::Standings, __('Nevobo-stand'), $standings[$nevoboTeam['nevobo_id']]];
            }

            foreach ($feeds as [$type, $name, $url]) {
                $counts['sources'] += $this->syncSource($team, $type, $name, $url) ? 1 : 0;
            }
        }

        $settings = PlannerSetting::current();
        $settings->update([
            'nevobo_club_code' => $clubCode,
            'team_name_patterns' => $this->patternsWith($settings->team_name_patterns ?? [], $clubName),
        ]);

        return $counts;
    }

    /**
     * Creates or updates the feed of this type that the linker manages: a
     * team feed (or pool standings) on the Nevobo API. Feeds someone added
     * by hand, such as a pool programme, stay as they are.
     *
     * @return bool whether a feed was created or its URL changed
     */
    private function syncSource(Team $team, TeamSourceType $type, string $name, string $url): bool
    {
        $prefix = $type === TeamSourceType::Standings
            ? NevoboClubDirectory::BASE_URL.'/export/poule/'
            : NevoboClubDirectory::BASE_URL.'/export/team/';
        $source = TeamSource::query()
            ->where('team_id', $team->id)
            ->where('type', $type)
            ->where(fn ($query) => $query->where('url', $url)->orWhere('url', 'like', $prefix.'%'))
            ->orderByRaw('url = ? desc', [$url])
            ->first();

        if ($source === null) {
            TeamSource::query()->create([
                'team_id' => $team->id,
                'name' => $name,
                'url' => $url,
                'type' => $type,
                'is_active' => true,
                'sort_order' => (int) TeamSource::query()->where('team_id', $team->id)->max('sort_order') + 1,
            ]);

            return true;
        }

        if ($source->url === $url) {
            return false;
        }

        $source->update(['url' => $url, 'last_error' => null]);

        return true;
    }

    /**
     * The club's own patterns plus the Nevobo club name, so the import
     * recognises "AETOS HS 1" as ours.
     *
     * @param  array<int, mixed>  $patterns
     * @return list<string>
     */
    private function patternsWith(array $patterns, ?string $clubName): array
    {
        $clean = array_values(array_filter(
            array_map(static fn (mixed $pattern): string => trim((string) $pattern), $patterns),
            static fn (string $pattern): bool => $pattern !== '',
        ));
        $clubName = trim((string) $clubName);

        $known = array_map('mb_strtolower', $clean);
        if ($clubName !== '' && !in_array(mb_strtolower($clubName), $known, true)) {
            $clean[] = $clubName;
        }

        return $clean;
    }
}
