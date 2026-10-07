<?php

namespace Tests\Unit\Planner;

use App\Planner\Support\CellValue;
use PHPUnit\Framework\TestCase;

class CellValueTest extends TestCase
{
    public function test_dates_from_text_and_excel_numbers(): void
    {
        $this->assertSame('2026-09-05', CellValue::date('05-09-2026'));
        $this->assertSame('2026-09-05', CellValue::date('2026-09-05'));
        $this->assertSame('2026-09-05', CellValue::date('05/09/2026'));
        $this->assertSame('2026-09-05', CellValue::date(46270));
        $this->assertNull(CellValue::date('n.t.b.'));
        $this->assertNull(CellValue::date(''));
    }

    public function test_times_from_text_and_excel_fractions(): void
    {
        $this->assertSame('19:30', CellValue::time('19:30:00'));
        $this->assertSame('19:30', CellValue::time(0.8125));
        $this->assertNull(CellValue::time('00:00'));
        $this->assertNull(CellValue::time(null));
    }
}
