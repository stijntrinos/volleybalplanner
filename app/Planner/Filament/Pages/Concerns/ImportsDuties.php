<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\DutyImportService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The Nevobo duty export import on the import page (module duties). It writes
 * referee/scorer assignments into matches, so it needs the match update right
 * and only targets drafts.
 *
 * @property-read Schema $dutyForm
 */
trait ImportsDuties
{
    /** @var array<string, mixed>|null */
    public ?array $dutyData = [];

    /** @var array<string, mixed>|null DutyImportService::analyze() result */
    public ?array $dutyReport = null;

    public function updatedDutyData(): void
    {
        $this->dutyReport = null;
    }

    public function dutyForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('1. Dienstenbestand kiezen'))
                ->description(__('Upload de dienstenexport van de Nevobo. Fluit- en teldiensten komen bij de wedstrijd, zaaldiensten als eigen regels.'))
                ->components([
                    $this->importFileUpload('file', __('Dienstenbestand'), 'duties')
                        ->helperText(__('Verplichte kolommen: :columns.', ['columns' => implode(', ', DutyImportService::REQUIRED)]))
                        ->columnSpanFull(),
                    Select::make('version_id')->label(__('Conceptversie'))
                        ->options(fn () => ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->pluck('name', 'id'))
                        ->required(),
                    Toggle::make('overwrite')
                        ->label(__('Bestaande toewijzingen overschrijven'))
                        ->helperText(__('Uit: wat de planner al heeft ingevuld blijft staan.')),
                ])->columns(2),
        ])->statePath('dutyData');
    }

    public function analyzeDuties(): void
    {
        abort_unless($this->hasModule(PlannerFeature::Duties), 403);
        $data = $this->dutyForm->getState();
        $path = $this->importPath($data['file'], 'duties');
        $this->attempt(__('Dienstenbestand is niet geldig'), function () use ($data, $path): void {
            $this->dutyReport = app(DutyImportService::class)->analyze(
                $path, $this->dutyVersion($data), (bool) ($data['overwrite'] ?? false),
            );
            Notification::make()->title(__('Dienstenbestand gecontroleerd'))->success()->send();
        });
    }

    public function importDuties(): void
    {
        abort_unless($this->hasModule(PlannerFeature::Duties), 403);
        abort_unless(auth()->user()?->can('Update:FixtureMatch'), 403);
        $data = $this->dutyForm->getState();
        $path = $this->importPath($data['file'], 'duties');
        $this->attempt(__('Diensten importeren mislukt'), function () use ($data, $path): void {
            $result = app(DutyImportService::class)->import(
                $path, $this->dutyVersion($data), (bool) ($data['overwrite'] ?? false),
            );
            $this->dutyReport = null;
            $this->deleteImportFile($data['file']);
            Notification::make()->title(__('Diensten geïmporteerd'))
                ->body(__(':referee fluitdiensten, :scorer teldiensten en :hall zaaldiensten toegewezen; :unmatched regels zonder wedstrijd.', [
                    'referee' => $result['assigned']['referee'], 'scorer' => $result['assigned']['scorer'],
                    'hall' => $result['assigned']['hall_duty'], 'unmatched' => count($result['unmatched']),
                ]))
                ->success()->send();
        });
    }

    private function dutyVersion(array $data): ScheduleVersion
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Draft)->findOrFail($data['version_id']);
    }
}
