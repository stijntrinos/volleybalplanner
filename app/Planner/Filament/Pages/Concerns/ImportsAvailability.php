<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Services\AvailabilityImportService;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The venue availability import on the import page.
 *
 * @property-read Schema $availabilityForm
 */
trait ImportsAvailability
{
    /** @var array<string, mixed>|null */
    public ?array $availabilityData = [];

    /** @var array<string, mixed>|null AvailabilityImportService::analyze() result */
    public ?array $availabilityReport = null;

    public function updatedAvailabilityData(): void
    {
        $this->availabilityReport = null;
    }

    public function availabilityForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('1. Beschikbaarheidsbestand kiezen'))
                ->description(__('Upload de zaalhuur van de gemeente en controleer het bestand voordat je beschikbaarheid bijwerkt.'))
                ->components([
                    $this->importFileUpload('file', __('Beschikbaarheidsbestand'), 'availability')
                        ->helperText(__('Kolommen: sporthal, scope (datum, weekdag of periode), datum, weekdag, periode van/tot, status, begintijd, eindtijd, veld en notitie.')),
                ]),
        ])->statePath('availabilityData');
    }

    public function analyzeAvailability(): void
    {
        $data = $this->availabilityForm->getState();
        $path = $this->importPath($data['file'], 'availability');
        $this->attempt(__('Beschikbaarheidsbestand is niet geldig'), function () use ($path): void {
            $this->availabilityReport = app(AvailabilityImportService::class)->analyze($path);
            Notification::make()->title(__('Beschikbaarheidsbestand gecontroleerd'))->success()->send();
        });
    }

    public function importAvailability(): void
    {
        abort_unless(auth()->user()?->can('Create:Availability'), 403);
        $data = $this->availabilityForm->getState();
        $path = $this->importPath($data['file'], 'availability');
        $this->attempt(__('Beschikbaarheid importeren mislukt'), function () use ($data, $path): void {
            $result = app(AvailabilityImportService::class)->import($path);
            $this->availabilityReport = null;
            $this->deleteImportFile($data['file']);
            Notification::make()->title(__('Beschikbaarheid geïmporteerd'))
                ->body(__(':created aangemaakt, :updated bijgewerkt, :skipped overgeslagen, :errors afgekeurd.', [
                    'created' => $result['created'], 'updated' => $result['updated'],
                    'skipped' => $result['skipped'], 'errors' => count($result['errors']),
                ]))
                ->success()->send();
        });
    }
}
