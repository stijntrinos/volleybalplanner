<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded import files live per club under planner-imports/{club}/{kind} on
 * the local disk and are removed after a successful import.
 */
trait HandlesImportFiles
{
    private const MAX_UPLOAD_KB = 10240;

    private const IMPORT_DIRECTORY = 'planner-imports';

    protected function importDirectory(string $kind): string
    {
        return self::IMPORT_DIRECTORY.'/'.app(CurrentClub::class)->id().'/'.$kind;
    }

    /**
     * The form state is a public Livewire property and thus writable by the
     * client. Without this check a path outside the club's import directory
     * could be put there.
     */
    protected function importPath(string|array|null $file, string $kind): string
    {
        $path = is_array($file) ? reset($file) : $file;
        $disk = Storage::disk('local');
        $full = $disk->path((string) $path);
        $base = $disk->path($this->importDirectory($kind));

        abort_unless(
            is_string($path) && $path !== '' && Str::startsWith(realpath($full) ?: '', (realpath($base) ?: $base).DIRECTORY_SEPARATOR),
            403,
            __('Dit bestand valt buiten de importmap.'),
        );

        return $full;
    }

    /** Uploaded source files have no use after the import. */
    protected function deleteImportFile(string|array|null $file): void
    {
        $path = is_array($file) ? reset($file) : $file;
        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }
    }

    protected function hasModule(PlannerFeature $feature): bool
    {
        return (bool) app(CurrentClub::class)->get()?->hasFeature($feature);
    }
}
