<?php

namespace App\Planner\Services;

use App\Planner\Models\PlannerSetting;
use App\Support\Tenancy\CurrentClub;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Writes a minimal xlsx workbook: one sheet per entry, the first row styled as
 * a frozen header with an autofilter.
 */
class SpreadsheetWriter
{
    /** Header fill when the club has no primary colour (ARGB). */
    public const DEFAULT_HEADER_COLOR = 'FF1F2937';

    /**
     * @param  array<string, list<array<int, mixed>>>  $sheets  sheet name => rows
     * @return string the path of the temporary xlsx file
     */
    public function write(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'planner-export-').'.xlsx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(__('Excelbestand kon niet worden aangemaakt.'));
        }
        $names = array_keys($sheets);
        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook($names));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->styles());
        foreach (array_values($sheets) as $index => $rows) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheet($rows));
        }
        $zip->close();

        return $path;
    }

    /** @param list<array<int, mixed>> $rows */
    private function sheet(array $rows): string
    {
        $columnCount = max(1, collect($rows)->map(fn (array $row): int => count($row))->max() ?? 1);
        $lastColumn = $this->column($columnCount);
        $columns = '<cols>';
        for ($column = 0; $column < $columnCount; $column++) {
            $width = collect($rows)->map(fn (array $row): int => mb_strlen((string) ($row[$column] ?? '')))->max() ?? 10;
            $width = min(42, max(10, $width + 2));
            $columns .= '<col min="'.($column + 1).'" max="'.($column + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $columns .= '</cols>';
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .$columns.'<sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="'.($r + 1).'"'.($r === 0 ? ' ht="24" customHeight="1"' : '').'>';
            foreach (array_values($row) as $c => $value) {
                $ref = $this->column($c + 1).($r + 1);
                $style = $r === 0 ? ' s="1"' : ' s="2"';
                $textValue = (string) $value;
                $isNumber = is_int($value) || is_float($value)
                    || (is_numeric($value) && !(strlen($textValue) > 1 && str_starts_with($textValue, '0')));
                if ($isNumber) {
                    $xml .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->escape($textValue).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData><autoFilter ref="A1:'.$lastColumn.max(1, count($rows)).'"/></worksheet>';
    }

    /** @param list<string|int> $names */
    private function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $index => $name) {
            $name = mb_substr(preg_replace('~[\\/?*\[\]:]~', '-', (string) $name), 0, 31) ?: __('Blad').' '.($index + 1);
            $sheets .= '<sheet name="'.$this->escape($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(int $count): string
    {
        $rels = '';
        for ($i = 1; $i <= $count; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function contentTypes(int $count): string
    {
        $overrides = '';
        for ($i = 1; $i <= $count; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos Display"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="'.$this->headerColor().'"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border/><border><bottom style="thin"><color rgb="FFDDE3EA"/></bottom></border></borders>'
            .'<cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="3">'
            .'<xf fontId="0" fillId="0" borderId="0"/><xf fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"><alignment vertical="center"/></xf>'
            .'<xf fontId="0" fillId="0" borderId="1" applyBorder="1"><alignment vertical="center"/></xf>'
            .'</cellXfs></styleSheet>';
    }

    /**
     * The header fill: the club's primary colour when a club is current and
     * has a valid "#rrggbb" colour, otherwise a neutral dark grey.
     */
    private function headerColor(): string
    {
        try {
            $color = app(CurrentClub::class)->get() !== null ? PlannerSetting::current()->primary_color : null;
        } catch (Throwable) {
            $color = null;
        }

        return is_string($color) && preg_match('/^#([0-9a-f]{6})$/i', $color, $match) === 1
            ? 'FF'.strtoupper($match[1])
            : self::DEFAULT_HEADER_COLOR;
    }

    private function column(int $number): string
    {
        $letters = '';
        while ($number > 0) {
            $number--;
            $letters = chr(65 + $number % 26).$letters;
            $number = intdiv($number, 26);
        }

        return $letters;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
