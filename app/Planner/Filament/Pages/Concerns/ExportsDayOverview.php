<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF and JPG exports of the day overview.
 *
 * Extracted from the HomeMatches page; the state lives here with the
 * behaviour that owns it.
 */
trait ExportsDayOverview
{
    public function exportDayPdf(): StreamedResponse
    {
        $content = app(ExportService::class)->daySchedulePdf(ScheduleVersion::query()->findOrFail($this->versionId), $this->date);

        return response()->streamDownload(fn () => print $content, "dagschema-{$this->date}.pdf", ['Content-Type' => 'application/pdf']);
    }

    public function exportDayJpg(): StreamedResponse
    {
        $content = app(ExportService::class)->dayScheduleJpg(ScheduleVersion::query()->findOrFail($this->versionId), $this->date);

        return response()->streamDownload(fn () => print $content, "dagschema-{$this->date}.jpg", ['Content-Type' => 'image/jpeg']);
    }
}
