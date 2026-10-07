<?php

namespace App\Planner\Console;

use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\MatchCode;
use App\Planner\Support\TeamCode;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The nightly sync stored the match code as the feed delivers it, and that
 * differs from the planning file in two ways: the district number in front,
 * and the sequence number of a repeated fixture. Both kept the sync from
 * recognising the match, so it created a second one.
 *
 * The feed is now translated on reading and the import has a safety net that
 * does not depend on the code, so no new ones appear. This command clears the
 * backlog: copies disappear, and matches only the sync knows keep their row
 * with the code in federation notation.
 */
class RepairMatchCodesCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:repair-match-codes
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}
                            {--apply : Daadwerkelijk opruimen; zonder deze vlag wordt alleen gerapporteerd}';

    protected $description = 'Verwijder dubbele wedstrijden uit de nachtsync en zet de overgebleven codes in bondsnotatie';

    public function handle(): int
    {
        return $this->forEachClub(fn (): int => $this->repair());
    }

    private function repair(): int
    {
        [$copies, $repairs, $unclear] = $this->classify();

        $this->report($copies, $repairs, $unclear);

        if ($copies->isEmpty() && $repairs->isEmpty()) {
            $this->components->info(__('Niets op te ruimen.'));

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->components->warn(__('Alleen gerapporteerd. Draai opnieuw met --apply om het door te voeren.'));

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && !$this->components->confirm(
            __(':copies wedstrijden verwijderen en :repairs codes bijwerken?', ['copies' => $copies->count(), 'repairs' => $repairs->count()]),
            false,
        )) {
            $this->components->info(__('Afgebroken.'));

            return self::SUCCESS;
        }

        FixtureMatch::query()->whereKey($copies->pluck('id'))->delete();

        foreach ($repairs as ['match' => $match, 'code' => $code]) {
            FixtureMatch::query()->whereKey($match->id)->update(['match_code' => $code]);
        }

        activity('planner-import')
            ->withProperties([
                'deleted' => $copies->count(),
                'renamed' => $repairs->count(),
                'codes' => $copies->pluck('match_code')->all(),
            ])
            ->log('Dubbele wedstrijden uit de nachtsync opgeruimd en codes in bondsnotatie gezet');

        $this->components->info(__(':copies verwijderd, :repairs codes bijgewerkt.', ['copies' => $copies->count(), 'repairs' => $repairs->count()]));

        return self::SUCCESS;
    }

    /**
     * A row with a district number next to the federation code of the same
     * match in the same version is the copy. On its own the sync knows a match
     * the planning file did not have: it stays, with its code repaired so the
     * next sync finds it.
     *
     * @return array{0: Collection<int, FixtureMatch>, 1: Collection<int, array{match: FixtureMatch, code: string}>, 2: Collection<int, FixtureMatch>}
     */
    private function classify(): array
    {
        $all = FixtureMatch::query()
            ->select(['id', 'club_id', 'version_id', 'match_code', 'date', 'time', 'home_team_name', 'away_team_name',
                'referee', 'referee_auto', 'scorer', 'scorer_auto', 'created_at'])
            ->orderBy('version_id')->orderBy('date')
            ->get();

        $toRepair = $all
            ->map(fn (FixtureMatch $match): array => ['match' => $match, 'code' => MatchCode::repairStored($match->match_code)])
            ->filter(fn (array $row): bool => $row['code'] !== null);

        // Codes with a district number that cannot be traced back unambiguously.
        $unclear = $all
            ->filter(fn (FixtureMatch $match): bool => str_starts_with((string) $match->match_code, '5000'))
            ->reject(fn (FixtureMatch $match): bool => MatchCode::repairStored($match->match_code) !== null)
            ->values();

        $existing = $all
            ->reject(fn (FixtureMatch $match): bool => str_starts_with((string) $match->match_code, '5000'))
            ->keyBy(fn (FixtureMatch $match): string => $match->version_id.'|'.mb_strtoupper((string) $match->match_code));

        $copies = collect();
        $repairs = collect();
        $kept = collect();

        foreach ($toRepair as $row) {
            $key = $row['match']->version_id.'|'.mb_strtoupper($row['code']);

            // Two sync rows tracing back to the same federation code: the
            // first gets the code, the rest is a copy.
            if ($existing->has($key) || $kept->has($key)) {
                $copies->push($row['match']);

                continue;
            }

            $kept->put($key, true);
            $repairs->push($row);
        }

        // Second round, independent of the code's shape: when the same match
        // is there twice, the oldest stays. That is the row from the planning
        // file; the newer one comes from a sync that did not recognise it.
        $copies = $copies->merge($this->duplicateMatches($all, $copies));

        // Manually assigned referees or scorers would disappear with the row;
        // those are reported instead of deleted.
        $unclear = $unclear->merge($copies->filter(fn (FixtureMatch $row): bool => $this->isManualWork($row)));

        return [
            $copies->reject(fn (FixtureMatch $row): bool => $this->isManualWork($row))->values(),
            $repairs->values(),
            $unclear->values(),
        ];
    }

    /**
     * Two teams cannot play each other twice at the same moment, so such rows
     * are the same match. Which code is right cannot be told from outside;
     * the oldest comes from the planning file and carries the planner's
     * choices, so it stays.
     *
     * @param  Collection<int, FixtureMatch>  $all
     * @param  Collection<int, FixtureMatch>  $alreadyReported
     * @return Collection<int, FixtureMatch>
     */
    private function duplicateMatches(Collection $all, Collection $alreadyReported): Collection
    {
        $reported = $alreadyReported->pluck('id')->all();

        return $all
            ->reject(fn (FixtureMatch $row): bool => in_array($row->id, $reported, true))
            ->groupBy(fn (FixtureMatch $row): string => implode('|', [
                $row->version_id,
                $row->date?->toDateString(),
                mb_substr((string) $row->time, 0, 5),
                TeamCode::key($row->home_team_name),
                TeamCode::key($row->away_team_name),
            ]))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->flatMap(fn (Collection $group): Collection => $group
                ->sortBy(fn (FixtureMatch $row): string => (string) $row->created_at)
                ->slice(1))
            ->values();
    }

    private function isManualWork(FixtureMatch $match): bool
    {
        return (filled($match->referee) && !$match->referee_auto)
            || (filled($match->scorer) && !$match->scorer_auto);
    }

    /**
     * @param  Collection<int, FixtureMatch>  $copies
     * @param  Collection<int, array{match: FixtureMatch, code: string}>  $repairs
     * @param  Collection<int, FixtureMatch>  $unclear
     */
    private function report(Collection $copies, Collection $repairs, Collection $unclear): void
    {
        $versions = ScheduleVersion::query()
            ->whereIn('id', $copies->pluck('version_id')->merge($repairs->pluck('match.version_id'))->filter()->unique())
            ->get()->keyBy('id');

        $this->components->twoColumnDetail('<fg=gray>'.__('Versie').'</>', '<fg=gray>'.__('Verwijderen · code bijwerken').'</>');
        foreach ($copies->groupBy('version_id') as $versionId => $rows) {
            $version = $versions->get($versionId);
            $this->components->twoColumnDetail(
                $version ? $version->name.' ('.$version->status->getLabel().')' : __('zonder versie'),
                $rows->count().' · '.$repairs->where('match.version_id', $versionId)->count(),
            );
        }

        foreach ($repairs->take(10) as $row) {
            $this->line('  <fg=green>'.__('hernoemen')."</> {$row['match']->match_code} -> {$row['code']}  "
                ."{$row['match']->home_team_name} - {$row['match']->away_team_name}");
        }
        if ($repairs->count() > 10) {
            $this->line('  <fg=gray>'.__('... en :count meer om te hernoemen', ['count' => $repairs->count() - 10]).'</>');
        }

        foreach ($copies->take(5) as $match) {
            $this->line('  <fg=red>'.__('verwijderen')."</> {$match->match_code}  {$match->date?->format('d-m-Y')}  "
                ."{$match->home_team_name} - {$match->away_team_name}");
        }
        if ($copies->count() > 5) {
            $this->line('  <fg=gray>'.__('... en :count meer om te verwijderen', ['count' => $copies->count() - 5]).'</>');
        }

        if ($unclear->isNotEmpty()) {
            $this->components->warn(__(':count rijen blijven staan en vragen om een blik:', ['count' => $unclear->count()]));
            foreach ($unclear->take(10) as $match) {
                $this->line("  <fg=gray>{$match->match_code}</> {$match->date?->format('d-m-Y')}  "
                    ."{$match->home_team_name} - {$match->away_team_name}");
            }
        }
    }
}
