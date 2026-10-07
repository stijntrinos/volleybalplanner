<?php

namespace App\Planner\Console;

use App\Planner\Console\Concerns\RunsPerClub;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\DutyImportService;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Console\Command;
use Throwable;

/**
 * Imports the Nevobo duty export (referee, scorer, hall duty per home match)
 * into a schedule version of one club. Without --apply it only reports what
 * would happen.
 *
 *   php artisan planner:import-duties "Diensten 2026-2027.xlsx" --club=demo --schedule-version=33
 *   php artisan planner:import-duties "Diensten 2026-2027.xlsx" --club=demo --schedule-version=33 --apply
 */
class ImportDutiesCommand extends Command
{
    use RunsPerClub;

    protected $signature = 'planner:import-duties
                            {file : Pad naar het xlsx- of csv-bestand met de diensten}
                            {--club= : Slug van de club}
                            {--all : Voor alle actieve clubs}
                            {--schedule-version= : Id of naam van de planningsversie waarin de diensten komen}
                            {--overwrite : Vervang ook toewijzingen die al ingevuld zijn}
                            {--apply : Daadwerkelijk wegschrijven; zonder deze vlag wordt alleen gerapporteerd}';

    protected $description = 'Importeer fluit-, tel- en zaaldiensten uit de Nevobo-dienstenexport in een planningsversie';

    public function handle(DutyImportService $service): int
    {
        $file = $this->file((string) $this->argument('file'));
        if ($file === null) {
            $this->components->error(__('Bestand niet gevonden: :file (ook niet in :path)', [
                'file' => $this->argument('file'), 'path' => base_path(),
            ]));

            return self::FAILURE;
        }

        return $this->forEachClub(fn (): int => $this->importFor($service, $file));
    }

    private function importFor(DutyImportService $service, string $file): int
    {
        $club = app(CurrentClub::class)->require();
        if (!$club->hasFeature(PlannerFeature::Duties)) {
            $this->components->warn(__(':club: de module diensten staat niet aan, overgeslagen.', ['club' => $club->name]));

            return self::SUCCESS;
        }

        $version = $this->scheduleVersion();
        if (!$version) {
            return self::FAILURE;
        }

        $overwrite = (bool) $this->option('overwrite');

        try {
            $report = $service->analyze($file, $version, $overwrite);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->report($version, $report);

        $toDo = array_sum($report['assigned']);
        if ($toDo === 0) {
            $this->components->info(__('Niets toe te kennen.'));

            return self::SUCCESS;
        }

        if (!$this->option('apply')) {
            $this->components->warn(__('Alleen gerapporteerd. Draai opnieuw met --apply om het door te voeren.'));

            return self::SUCCESS;
        }

        if ($this->input->isInteractive() && !$this->components->confirm(
            __(':count diensten wegschrijven in versie ":name" (id :id)?', ['count' => $toDo, 'name' => $version->name, 'id' => $version->id]),
            false,
        )) {
            $this->components->info(__('Afgebroken.'));

            return self::SUCCESS;
        }

        $report = $service->import($file, $version, $overwrite);

        activity('planner-import')->performedOn($version)
            ->withProperties([
                'file' => basename($file), 'overwrite' => $overwrite,
                'assigned' => $report['assigned'], 'skipped' => count($report['skipped']),
                'unmatched' => count($report['unmatched']),
            ])
            ->log('Diensten geïmporteerd uit de Nevobo-dienstenexport');

        $this->components->info(__('Klaar: :referee fluit, :scorer tel, :hall zaaldienst toegekend.', [
            'referee' => $report['assigned']['referee'], 'scorer' => $report['assigned']['scorer'], 'hall' => $report['assigned']['hall_duty'],
        ]));

        return self::SUCCESS;
    }

    /**
     * A relative path counts from the working directory first and otherwise
     * from the project directory, so a file next to deploy.php is found by
     * name even when the command is started from another directory.
     */
    private function file(string $given): ?string
    {
        if (is_file($given)) {
            return $given;
        }
        if (!str_starts_with($given, DIRECTORY_SEPARATOR) && is_file(base_path($given))) {
            return base_path($given);
        }

        return null;
    }

    private function scheduleVersion(): ?ScheduleVersion
    {
        $choice = trim((string) $this->option('schedule-version'));
        $versions = ScheduleVersion::query()->orderByDesc('id');

        $version = $choice === '' ? null : (is_numeric($choice)
            ? (clone $versions)->whereKey((int) $choice)->first()
            : (clone $versions)->where('name', $choice)->first());

        if ($version) {
            return $version;
        }

        $this->components->error($choice === ''
            ? __('Geef met --schedule-version aan in welke planningsversie de diensten komen.')
            : __('Versie ":version" bestaat niet.', ['version' => $choice]));
        $this->table(
            [__('Id'), __('Naam'), __('Status')],
            $versions->limit(8)->get()->map(fn (ScheduleVersion $v): array => [$v->id, $v->name, $v->status->getLabel()])->all(),
        );

        return null;
    }

    /** @param  array<string, mixed>  $report */
    private function report(ScheduleVersion $version, array $report): void
    {
        $this->components->info(__('Versie ":name" (id :id, :status), :rows rijen gelezen.', [
            'name' => $version->name, 'id' => $version->id, 'status' => $version->status->getLabel(), 'rows' => $report['rows'],
        ]));
        $this->table(
            [__('Fluitdienst'), __('Teldienst'), __('Zaaldienst'), __('Al gelijk'), __('Overgeslagen'), __('Niet gekoppeld'), __('Volleystars')],
            [[
                $report['assigned']['referee'], $report['assigned']['scorer'], $report['assigned']['hall_duty'],
                $report['unchanged'], count($report['skipped']), count($report['unmatched']), $report['volleystars'],
            ]],
        );

        $sections = [
            'skipped' => __('Overgeslagen (al ingevuld, gebruik --overwrite om te vervangen)'),
            'unmatched' => __('Niet gekoppeld'),
        ];
        foreach ($sections as $key => $heading) {
            if ($report[$key] === []) {
                continue;
            }
            $this->newLine();
            $this->components->twoColumnDetail("<fg=yellow>{$heading}</>", __(':count rijen', ['count' => count($report[$key])]));
            foreach ($report[$key] as ['row' => $row, 'reason' => $reason]) {
                $this->components->twoColumnDetail('  '.__('rij :row', ['row' => $row]), $reason);
            }
        }

        if ($report['volleystars'] > 0) {
            $this->newLine();
            $this->components->warn(__(':count Volleystar-diensten overgeslagen: die horen bij de VolleyStars-blokken en worden niet geïmporteerd.', [
                'count' => $report['volleystars'],
            ]));
        }
    }
}
