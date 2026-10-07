<?php

namespace App\Planner\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an xlsx file, or a csv file, as rows keyed by the
 * header row. Rows without any value are skipped.
 */
class SpreadsheetReader
{
    /** @return list<array<string, mixed>> */
    public function read(string $path): array
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'csv'
            ? $this->readCsv($path)
            : $this->readXlsx($path);
    }

    /** @return list<array<string, mixed>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException(__('Het importbestand kon niet worden geopend.'));
        }
        $first = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $rows = [];
        while (($row = fgetcsv($handle, separator: $delimiter, escape: '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $this->withHeaders($rows);
    }

    /** @return list<array<string, mixed>> */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException(__('Het xlsx-bestand kon niet worden geopend.'));
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $document = simplexml_load_string($xml);
            if ($document !== false) {
                foreach ($document->si ?? [] as $item) {
                    $shared[] = $this->text($item);
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new RuntimeException(__('Het eerste werkblad ontbreekt in het xlsx-bestand.'));
        }

        $sheet = simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException(__('Het eerste werkblad bevat geen geldige XML.'));
        }
        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                preg_match('/^[A-Z]+/', $reference, $match);
                $index = $this->columnIndex($match[0] ?? 'A');
                $type = (string) $cell['t'];
                $value = $type === 'inlineStr' ? $this->text($cell->is) : (string) $cell->v;
                if ($type === 's') {
                    $value = $shared[(int) $value] ?? '';
                }
                $values[$index] = $value;
            }
            if ($values) {
                $max = max(array_keys($values));
                $rows[] = array_map(fn (int $i) => $values[$i] ?? '', range(0, $max));
            }
        }

        return $this->withHeaders($rows);
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withHeaders(array $rows): array
    {
        $headers = array_map(fn ($header) => trim((string) $header), array_shift($rows) ?? []);

        return array_values(array_filter(array_map(function (array $row) use ($headers): array {
            $result = [];
            foreach ($headers as $index => $header) {
                $result[$header] = $row[$index] ?? null;
            }

            return $result;
        }, $rows), fn (array $row) => count(array_filter($row, fn ($v) => $v !== null && $v !== '')) > 0));
    }

    private function text(SimpleXMLElement $element): string
    {
        $text = (string) $element->t;
        foreach ($element->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }
}
