<?php

namespace Tests\Unit\Planner;

use App\Planner\Services\SpreadsheetReader;
use App\Planner\Services\SpreadsheetWriter;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetTest extends TestCase
{
    public function test_a_written_workbook_reads_back_with_headers(): void
    {
        $path = (new SpreadsheetWriter)->write([
            'Wedstrijden: september' => [
                ['Code', 'Datum', 'Thuis', 'Postcode'],
                ['ABC-1', '2026-09-12', 'Club & Co <HS 1>', '0123'],
                ['', '', '', ''],
                ['ABC-2', '2026-09-19', 'Club DS 1', 42],
            ],
        ]);

        $rows = (new SpreadsheetReader)->read($path);
        $zip = new ZipArchive;
        $zip->open($path);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame(['Code' => 'ABC-1', 'Datum' => '2026-09-12', 'Thuis' => 'Club & Co <HS 1>', 'Postcode' => '0123'], $rows[0]);
        $this->assertSame('42', $rows[1]['Postcode']);
        $this->assertStringContainsString('name="Wedstrijden- september"', $workbook);
    }

    public function test_csv_with_semicolons_is_read(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'planner-test-').'.csv';
        file_put_contents($path, "Code;Team\nABC-1;Club HS 1\n;\n");

        $rows = (new SpreadsheetReader)->read($path);
        unlink($path);

        $this->assertSame([['Code' => 'ABC-1', 'Team' => 'Club HS 1']], $rows);
    }
}
