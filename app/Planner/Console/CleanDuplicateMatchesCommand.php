<?php

namespace App\Planner\Console;

use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\Import\RssMatchIndex;
use App\Planner\Support\TeamCode;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The RSS sync used to deduplicate on match code only. A feed without a code
 * got one of its own ("RSS-..."), so a match already present under its real
 * federation code got a second row next to it. That copy had no venue of its
 * own and so counted as an away match, after which the duty schedule reported
 * an away match for the team on the day of its own home match.
 *
 * This command clears the backlog; the sync now also recognises such matches
 * by their teams, so no new ones appear.
 */
class CleanDuplicateMatchesCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:clean-duplicate-matches
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}
                            {--delete : Daadwerkelijk verwijderen; zonder deze vlag wordt alleen gerapporteerd}';

    protected $description = 'Verwijder RSS-kopieën van wedstrijden die al onder hun bondscode in de planning staan';

    public function handle(): int
    {
        return $this->forEachClub(fn (): int => $this->clean());
    }

    private function clean(): int
    {
        [$copies, $kept] = $this->copies();

        if ($kept->isNotEmpty()) {
            $this->components->warn(__(':count kopieën hebben zelf een toewijzing en blijven staan:', ['count' => $kept->count()]));
            foreach ($kept->take(10) as $match) {
                $this->line("  <fg=gray>{$match->match_code}</> {$match->home_team_name} - {$match->away_team_name}");
            }
        }

        if ($copies->isEmpty()) {
            $this->components->info(__('Geen dubbele wedstrijden gevonden.'));

            return self::SUCCESS;
        }

        $versions = ScheduleVersion::query()
            ->whereIn('id', $copies->pluck('version_id')->filter()->unique())
            ->get()->keyBy('id');

        $this->components->twoColumnDetail('<fg=gray>'.__('Versie').'</>', '<fg=gray>'.__('Kopieën').'</>');
        foreach ($copies->groupBy('version_id') as $versionId => $rows) {
            $version = $versions->get($versionId);
            $this->components->twoColumnDetail(
                $version ? $version->name.' ('.$version->status->getLabel().')' : __('zonder versie'),
                (string) $rows->count(),
            );
        }

        foreach ($copies->take(10) as $match) {
            $this->line("  <fg=gray>{$match->match_code}</> {$match->date?->format('d-m-Y')} "
                ."{$match->home_team_name} - {$match->away_team_name}");
        }
        if ($copies->count() > 10) {
            $this->line('  <fg=gray>'.__('... en :count meer', ['count' => $copies->count() - 10]).'</>');
        }

        if (!$this->option('delete')) {
            $this->components->warn(__('Alleen gerapporteerd. Draai opnieuw met --delete om ze te verwijderen.'));

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && !$this->components->confirm(__(':count kopieën verwijderen?', ['count' => $copies->count()]), false)) {
            $this->components->info(__('Afgebroken.'));

            return self::SUCCESS;
        }

        FixtureMatch::query()->whereKey($copies->pluck('id'))->delete();

        activity('planner-import')
            ->withProperties(['count' => $copies->count(), 'codes' => $copies->pluck('match_code')->all()])
            ->log('Dubbele wedstrijden uit de RSS-sync verwijderd');

        $this->components->info(__(':count dubbele wedstrijden verwijderd.', ['count' => $copies->count()]));

        return self::SUCCESS;
    }

    /**
     * A copy is a match with a synthetic code that sits next to a match with
     * a real federation code in the same version, on the same day, between
     * the same teams. Groups with only synthetic or only real codes are left
     * alone: those are separate matches, and which is right cannot be decided
     * here.
     *
     * @return array{0: Collection<int, FixtureMatch>, 1: Collection<int, FixtureMatch>}
     */
    private function copies(): array
    {
        $candidates = FixtureMatch::query()
            ->select(['id', 'club_id', 'version_id', 'match_code', 'date', 'home_team_name', 'away_team_name', 'referee', 'scorer'])
            ->orderBy('version_id')->orderBy('date')
            ->get()
            ->groupBy(fn (FixtureMatch $match): string => implode('|', [
                $match->version_id,
                $match->date?->toDateString(),
                TeamCode::key($match->home_team_name),
                TeamCode::key($match->away_team_name),
            ]))
            ->filter(fn (Collection $group): bool => $group->count() > 1
                && $group->contains(fn (FixtureMatch $row): bool => RssMatchIndex::isSynthetic($row->match_code))
                && $group->contains(fn (FixtureMatch $row): bool => !RssMatchIndex::isSynthetic($row->match_code)))
            ->flatMap(fn (Collection $group): Collection => $group->filter(fn (FixtureMatch $row): bool => RssMatchIndex::isSynthetic($row->match_code)))
            ->values();

        // A copy that got a referee or scorer of its own would throw work
        // away; it is reported instead of deleted.
        return [
            $candidates->reject(fn (FixtureMatch $row): bool => filled($row->referee) || filled($row->scorer))->values(),
            $candidates->filter(fn (FixtureMatch $row): bool => filled($row->referee) || filled($row->scorer))->values(),
        ];
    }
}
