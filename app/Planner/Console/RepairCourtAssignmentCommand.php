<?php

namespace App\Planner\Console;

use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * The nightly sync read a venue name with its address behind it as a venue
 * change and took over the team's default court. That lost the planner's
 * court assignment. The comparison has been fixed; this command restores the
 * assignment from a version the sync did not touch, usually the latest
 * published one.
 */
class RepairCourtAssignmentCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:repair-court-assignment
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}
                            {--to= : Id van de versie die hersteld wordt; standaard de nieuwste conceptversie}
                            {--from= : Id van de versie waaruit de indeling komt; standaard de nieuwste gepubliceerde}
                            {--apply : Daadwerkelijk terugzetten; zonder deze vlag wordt alleen gerapporteerd}';

    protected $description = 'Zet de veldindeling van een planningversie terug zoals die vóór de nachtsync was';

    public function handle(): int
    {
        return $this->forEachClub(fn (): int => $this->repair());
    }

    private function repair(): int
    {
        $to = $this->version($this->option('to'), ScheduleVersionStatus::Draft, __('conceptversie'));
        $from = $this->version($this->option('from'), ScheduleVersionStatus::Published, __('gepubliceerde versie'));

        if (!$to || !$from) {
            return self::FAILURE;
        }

        if ($to->is($from)) {
            $this->components->error(__('Bron en doel zijn dezelfde versie.'));

            return self::FAILURE;
        }

        $deviating = $this->deviating($from, $to);

        $this->components->twoColumnDetail('<fg=gray>'.__('Bron').'</>', $from->name.' ('.$from->status->getLabel().')');
        $this->components->twoColumnDetail('<fg=gray>'.__('Doel').'</>', $to->name.' ('.$to->status->getLabel().')');
        $this->components->twoColumnDetail('<fg=gray>'.__('Terug te zetten').'</>', (string) $deviating->count());
        $this->components->twoColumnDetail(
            '<fg=gray>'.__('Dubbel geboekte velden').'</>',
            $this->collisions($to->id, $deviating).' → '.$this->collisions($to->id, $deviating, restored: true),
        );

        foreach ($deviating->take(10) as $row) {
            $this->line("  <fg=gray>{$row['match']->match_code}</> {$row['match']->date?->format('d-m-Y')} "
                .__('veld')." {$this->courtLabel($row['match']->court_id)} → {$this->courtLabel($row['source']->court_id)}");
        }
        if ($deviating->count() > 10) {
            $this->line('  <fg=gray>'.__('... en :count meer', ['count' => $deviating->count() - 10]).'</>');
        }

        if ($deviating->isEmpty()) {
            $this->components->info(__('De veldindeling komt al overeen.'));

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->components->warn(__('Alleen gerapporteerd. Draai opnieuw met --apply om het terug te zetten.'));

            return self::SUCCESS;
        }

        if ($this->input->isInteractive()
            && !$this->components->confirm(__(':count wedstrijden terugzetten?', ['count' => $deviating->count()]), false)) {
            $this->components->info(__('Afgebroken.'));

            return self::SUCCESS;
        }

        foreach ($deviating as $row) {
            FixtureMatch::query()->whereKey($row['match']->id)->update([
                'court_id' => $row['source']->court_id,
                'second_court_id' => $row['source']->second_court_id,
            ]);
        }

        activity('planner-import')
            ->withProperties(['count' => $deviating->count(), 'from' => $from->id, 'to' => $to->id])
            ->log('Veldindeling teruggezet na een zaalwijziging die de nachtsync ten onrechte doorvoerde');

        $this->components->info(__(':count wedstrijden teruggezet.', ['count' => $deviating->count()]));

        return self::SUCCESS;
    }

    /**
     * Matches are paired by match code: unique per version and unchanged when
     * a match is moved.
     *
     * @return Collection<int, array{match: FixtureMatch, source: FixtureMatch}>
     */
    private function deviating(ScheduleVersion $from, ScheduleVersion $to): Collection
    {
        $source = FixtureMatch::query()
            ->where('version_id', $from->id)
            ->get(['id', 'match_code', 'court_id', 'second_court_id'])
            ->keyBy('match_code');

        return FixtureMatch::query()
            ->where('version_id', $to->id)
            ->get(['id', 'version_id', 'match_code', 'date', 'time', 'court_id', 'second_court_id'])
            ->filter(fn (FixtureMatch $match): bool => $source->has((string) $match->match_code))
            ->map(fn (FixtureMatch $match): array => [
                'match' => $match,
                'source' => $source->get((string) $match->match_code),
            ])
            ->filter(fn (array $row): bool => $row['match']->court_id !== $row['source']->court_id
                || $row['match']->second_court_id !== $row['source']->second_court_id)
            ->values();
    }

    /**
     * Two matches on the same court and moment, with the current assignment
     * or as it would be after restoring.
     *
     * @param  Collection<int, array{match: FixtureMatch, source: FixtureMatch}>  $deviating
     */
    private function collisions(int $versionId, Collection $deviating, bool $restored = false): int
    {
        $restore = $deviating->keyBy(fn (array $row): int => $row['match']->id);

        return FixtureMatch::query()
            ->where('version_id', $versionId)
            ->get(['id', 'date', 'time', 'court_id'])
            ->map(fn (FixtureMatch $match): string => implode('|', [
                $match->date?->toDateString(),
                mb_substr((string) $match->time, 0, 5),
                $restored ? ($restore->get($match->id)['source']->court_id ?? $match->court_id) : $match->court_id,
            ]))
            ->reject(fn (string $key): bool => str_ends_with($key, '|'))
            ->countBy()
            ->filter(fn (int $count): bool => $count > 1)
            ->count();
    }

    private function version(mixed $id, ScheduleVersionStatus $status, string $description): ?ScheduleVersion
    {
        $version = $id
            ? ScheduleVersion::query()->find($id)
            : ScheduleVersion::query()->where('status', $status)->latest('id')->first();

        if (!$version) {
            $this->components->error($id
                ? __('Versie :id bestaat niet.', ['id' => $id])
                : __('Geen :description gevonden.', ['description' => $description]));
        }

        return $version;
    }

    private function courtLabel(?int $courtId): string
    {
        return $courtId === null ? __('geen') : (string) $courtId;
    }
}
