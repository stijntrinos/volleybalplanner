<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Services\ImportCleanupService;
use Filament\Notifications\Notification;

/** The cleanup of imported data per category on the import page. */
trait CleansUpImports
{
    /** @var array<string, bool> */
    public array $cleanupSelection = [];

    /** @var array<string, int> */
    public array $cleanupCounts = [];

    /** @var array<string, int> */
    public array $cleanupCascade = [];

    /** @var array<string, string> */
    public array $cleanupCategories = [];

    public bool $publishedVersionProtected = false;

    public function cleanup(): void
    {
        $categories = collect($this->cleanupSelection)->filter()->keys()->all();
        if ($categories === []) {
            Notification::make()->title(__('Selecteer minimaal één categorie'))->warning()->send();

            return;
        }

        $deleted = app(ImportCleanupService::class)->cleanup($categories);
        $this->cleanupSelection = [];
        $this->refreshCleanupOverview();
        Notification::make()->title(__('Importgegevens opgeschoond'))
            ->body(__(':count records verwijderd.', ['count' => array_sum($deleted)]))
            ->success()->send();
    }

    private function refreshCleanupOverview(): void
    {
        $service = app(ImportCleanupService::class);
        $this->cleanupCategories = $service->categories();
        $this->cleanupCounts = $service->counts();
        $this->cleanupCascade = $service->cascadeCounts();
        $this->publishedVersionProtected = $service->hasPublishedVersion();
    }
}
