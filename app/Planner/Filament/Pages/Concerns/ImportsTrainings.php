<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Features\PlannerFeature;
use App\Planner\Services\TrainingImportService;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The training schedule import on the import page (module trainings).
 *
 * @property-read Schema $trainingForm
 */
trait ImportsTrainings
{
    /** @var array<string, mixed>|null */
    public ?array $trainingData = [];

    /** @var array<string, mixed>|null TrainingImportService::analyze() result */
    public ?array $trainingReport = null;

    public function updatedTrainingData(): void
    {
        $this->trainingReport = null;
    }

    public function trainingForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('1. Trainingsbestand kiezen'))
                ->description(__('Upload het actuele trainingsschema en controleer het bestand voordat je gegevens bijwerkt.'))
                ->components([
                    $this->importFileUpload('file', __('Trainingsbestand'), 'trainings')
                        ->helperText(__('Ondersteunde kolommen: Sporthal, Dag, Begintijd, Eindtijd, Type veld, Veld en Team.')),
                ]),
        ])->statePath('trainingData');
    }

    public function analyzeTrainings(): void
    {
        abort_unless($this->hasModule(PlannerFeature::Trainings), 403);
        $data = $this->trainingForm->getState();
        $path = $this->importPath($data['file'], 'trainings');
        $this->attempt(__('Trainingsbestand is niet geldig'), function () use ($path): void {
            $this->trainingReport = app(TrainingImportService::class)->analyze($path);
            Notification::make()->title(__('Trainingsbestand gecontroleerd'))->success()->send();
        });
    }

    public function importTrainings(): void
    {
        abort_unless($this->hasModule(PlannerFeature::Trainings), 403);
        $data = $this->trainingForm->getState();
        $path = $this->importPath($data['file'], 'trainings');
        $this->attempt(__('Trainingsimport mislukt'), function () use ($data, $path): void {
            $result = app(TrainingImportService::class)->import($path, auth()->id());
            $this->trainingReport = null;
            $this->deleteImportFile($data['file']);
            Notification::make()->title(__('Trainingsschema geïmporteerd'))
                ->body(__(':created aangemaakt, :updated bijgewerkt, :skipped regels zonder team overgeslagen, :errors afgekeurd.', [
                    'created' => $result['created'], 'updated' => $result['updated'],
                    'skipped' => $result['skipped'], 'errors' => count($result['errors']),
                ]))
                ->success()->send();
        });
    }
}
