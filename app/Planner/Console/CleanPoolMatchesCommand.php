<?php

namespace App\Planner\Console;

use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Support\ClubTeamMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The RSS sources are pool feeds, so until the sync filtered them they also
 * delivered matches between two other clubs. Those sit in the planning as
 * away matches and show up in pending changes when moved, although none of
 * it concerns us. This command clears the backlog; no new ones appear.
 */
class CleanPoolMatchesCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:clean-pool-matches
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}
                            {--delete : Daadwerkelijk verwijderen; zonder deze vlag wordt alleen gerapporteerd}';

    protected $description = 'Verwijder geïmporteerde poulewedstrijden waarin geen eigen team speelt';

    public function handle(): int
    {
        return $this->forEachClub(fn (): int => $this->clean());
    }

    private function clean(): int
    {
        $matcher = ClubTeamMatcher::forCurrentClub();
        if (!$matcher->hasPatterns()) {
            // Without club name patterns no team is recognised as ours, and
            // every match would look foreign.
            $this->components->warn(__('Er zijn geen teamnaampatronen ingesteld; er wordt niets opgeschoond.'));

            return self::SUCCESS;
        }

        $matches = $this->foreignMatches($matcher);

        if ($matches->isEmpty()) {
            $this->components->info(__('Geen poulewedstrijden zonder eigen team gevonden.'));

            return self::SUCCESS;
        }

        $versions = ScheduleVersion::query()
            ->whereIn('id', $matches->pluck('version_id')->filter()->unique())
            ->get()->keyBy('id');

        $this->components->twoColumnDetail('<fg=gray>'.__('Versie').'</>', '<fg=gray>'.__('Wedstrijden').'</>');
        foreach ($matches->groupBy('version_id') as $versionId => $rows) {
            $version = $versions->get($versionId);
            $label = $version ? $version->name.' ('.$version->status->getLabel().')' : __('zonder versie');
            $this->components->twoColumnDetail($label, (string) $rows->count());
        }

        foreach ($matches->take(10) as $match) {
            $this->line("  <fg=gray>{$match->match_code}</> {$match->home_team_name} - {$match->away_team_name}");
        }
        if ($matches->count() > 10) {
            $this->line('  <fg=gray>'.__('... en :count meer', ['count' => $matches->count() - 10]).'</>');
        }

        if (!$this->option('delete')) {
            $this->components->warn(__('Alleen gerapporteerd. Draai opnieuw met --delete om ze te verwijderen.'));

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && !$this->components->confirm(__(':count wedstrijden verwijderen?', ['count' => $matches->count()]), false)) {
            $this->components->info(__('Afgebroken.'));

            return self::SUCCESS;
        }

        FixtureMatch::query()->whereKey($matches->pluck('id'))->delete();

        activity('planner-import')
            ->withProperties(['count' => $matches->count(), 'codes' => $matches->pluck('match_code')->all()])
            ->log('Poulewedstrijden zonder eigen team verwijderd');

        $this->components->info(__(':count poulewedstrijden verwijderd.', ['count' => $matches->count()]));

        return self::SUCCESS;
    }

    /**
     * The club test is not in SQL: team names vary too much in spelling, and
     * the same test as the import belongs in one place.
     *
     * @return Collection<int, FixtureMatch>
     */
    private function foreignMatches(ClubTeamMatcher $matcher): Collection
    {
        return FixtureMatch::query()
            ->select(['id', 'club_id', 'version_id', 'match_code', 'home_team_name', 'away_team_name'])
            ->orderBy('version_id')->orderBy('date')
            ->get()
            ->reject(fn (FixtureMatch $match): bool => $matcher->isOwnTeam($match->home_team_name)
                || $matcher->isOwnTeam($match->away_team_name))
            ->values();
    }
}
