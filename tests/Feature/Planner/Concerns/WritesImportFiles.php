<?php

namespace Tests\Feature\Planner\Concerns;

/**
 * Temporary CSV files for import tests, removed after each test.
 */
trait WritesImportFiles
{
    /** @var list<string> */
    private array $importFiles = [];

    protected function tearDownWritesImportFiles(): void
    {
        foreach ($this->importFiles as $file) {
            @unlink($file);
        }
        $this->importFiles = [];
    }

    /**
     * A Nevobo planning export with the given rows.
     *
     * @param  list<list<string>>  $rows
     */
    protected function planningCsv(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'planner-import-').'.csv';
        $handle = fopen($file, 'wb');
        fputcsv($handle, ['Datum', 'Tijd', 'Team thuis', 'Team uit', 'Zaal', 'Veld', 'Regio', 'Poule', 'Code', 'Zaalcode', 'Plaats'], ';', escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', escape: '');
        }
        fclose($handle);
        $this->importFiles[] = $file;

        return $file;
    }

    /** A CSV file with the given raw lines (the first line is the header). */
    protected function rawCsv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'planner-import-').'.csv';
        file_put_contents($file, $contents);
        $this->importFiles[] = $file;

        return $file;
    }
}
