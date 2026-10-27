<?php

namespace App\Planner\Services\Duties;

use App\Planner\Models\Team;
use App\Planner\Support\TeamCode;
use Illuminate\Support\Facades\DB;

/**
 * Reads the list the volunteer committee hands in per season half: per team
 * need, earned, still to fill, percentage and minimum percentage. The list is
 * pasted from a spreadsheet (tabs) or as CSV (semicolon or comma). Need,
 * earned and minimum percentage are taken over; still to fill and percentage
 * the planner computes itself, so they are ignored. In the second half it is
 * a corrected standing for the whole season, so the values replace what was
 * there. Works on the current club.
 */
class PointsListImport
{
    /** The columns in the order of the list when there is no header row. */
    public const COLUMNS = ['team', 'need', 'earned', 'remaining', 'percentage', 'minimum_percentage'];

    /** @return array{updated: list<string>, unknown: list<string>, errors: list<string>} */
    public function import(string $text): array
    {
        $result = ['updated' => [], 'unknown' => [], 'errors' => []];
        $teams = Team::query()->get()->keyBy(fn (Team $team): string => TeamCode::key($team->code));

        DB::transaction(function () use ($text, $teams, &$result): void {
            foreach ($this->rows($text) as $line => $row) {
                $team = $teams->get(TeamCode::key($row['team'] ?? ''));
                if ($team === null) {
                    $result['unknown'][] = (string) ($row['team'] ?? __('regel :line', ['line' => $line]));

                    continue;
                }

                $values = $this->values($row);
                if (is_string($values)) {
                    $result['errors'][] = "{$team->code}: {$values}";

                    continue;
                }

                $team->update($values);
                $result['updated'][] = (string) $team->code;
            }
        });

        return $result;
    }

    /**
     * The lines as arrays with column names. A header row is recognised by
     * the word "team"; without one the columns of COLUMNS apply.
     *
     * @return array<int, array<string, string>>
     */
    public function rows(string $text): array
    {
        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($text)) ?: [],
            fn (string $line): bool => trim($line) !== '',
        ));
        if ($lines === []) {
            return [];
        }

        $separator = $this->separator($lines[0]);
        $columns = self::COLUMNS;
        $first = array_map('trim', str_getcsv($lines[0], $separator));
        if (preg_grep('/^team/i', $first)) {
            $columns = array_map(fn (string $header): string => $this->columnOfHeader($header), $first);
            array_shift($lines);
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            $cells = array_map('trim', str_getcsv($line, $separator));
            $row = [];
            foreach ($columns as $position => $column) {
                if ($column !== '' && array_key_exists($position, $cells)) {
                    $row[$column] = $cells[$position];
                }
            }
            $rows[$index + 1] = $row;
        }

        return $rows;
    }

    /** @return array<string, int|null>|string the new values, or why the line is not right */
    private function values(array $row): array|string
    {
        $values = [];
        foreach (['need' => 'volunteer_points_used', 'earned' => 'volunteer_points_earned'] as $column => $field) {
            if (!array_key_exists($column, $row) || $row[$column] === '') {
                continue;
            }
            $number = $this->number($row[$column]);
            if ($number === null || $number < 0) {
                return __(':column \':value\' is geen geheel getal vanaf 0.', [
                    'column' => $column === 'need' ? __('Behoefte') : __('Geleverd'),
                    'value' => $row[$column],
                ]);
            }
            $values[$field] = $number;
        }

        if (array_key_exists('minimum_percentage', $row)) {
            // Empty means 100%: the list may deliver it like that too.
            $number = $row['minimum_percentage'] === '' ? null : $this->number($row['minimum_percentage']);
            if ($row['minimum_percentage'] !== '' && ($number === null || $number < 0 || $number > 100)) {
                return __('Minimumpercentage \':value\' ligt niet tussen 0 en 100.', ['value' => $row['minimum_percentage']]);
            }
            $values['volunteer_points_percentage'] = $number;
        }

        return $values === [] ? __('Geen behoefte, geleverd of minimumpercentage gevonden.') : $values;
    }

    /** "1.234", "218,4" and "70%" become 1234, 218 and 70. */
    private function number(string $value): ?int
    {
        $value = str_replace(['%', ' ', "\u{00A0}"], '', $value);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    private function separator(string $line): string
    {
        foreach (["\t", ';', ','] as $character) {
            if (str_contains($line, $character)) {
                return $character;
            }
        }

        return "\t";
    }

    private function columnOfHeader(string $header): string
    {
        $header = strtolower(trim($header));

        return match (true) {
            str_starts_with($header, 'team') => 'team',
            str_starts_with($header, 'behoefte') || str_contains($header, 'ontvangen') => 'need',
            str_starts_with($header, 'geleverd') => 'earned',
            str_starts_with($header, 'nog') => 'remaining',
            // "Minimum" itself is an outcome, not input; only the percentage counts.
            str_starts_with($header, 'minimum') && (str_contains($header, 'perc') || str_contains($header, '%')) => 'minimum_percentage',
            str_starts_with($header, 'percentage') || $header === '%' => 'percentage',
            default => '',
        };
    }
}
