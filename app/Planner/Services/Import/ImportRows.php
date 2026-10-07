<?php

namespace App\Planner\Services\Import;

use App\Planner\Enums\MatchType;
use App\Planner\Support\CellValue;
use App\Planner\Support\ClubTeamMatcher;
use Carbon\CarbonImmutable;

/**
 * Validates and normalises the rows of a Nevobo planning export (Excel/CSV).
 * The column headers are those of the federation export and stay Dutch.
 */
class ImportRows
{
    /** Columns every row must fill. */
    public const REQUIRED = ['Code', 'Team thuis', 'Team uit', 'Zaal', 'Veld'];

    /**
     * Why a raw row cannot be used, or null when it can.
     *
     * @param  array<string, mixed>  $row
     */
    public function invalidReason(array $row): ?string
    {
        foreach (self::REQUIRED as $column) {
            if (trim((string) ($row[$column] ?? '')) === '') {
                return __(':column is leeg', ['column' => $column]);
            }
        }

        $date = trim((string) ($row['Datum'] ?? ''));
        if ($date !== '' && CellValue::date($date) === null) {
            return __('Datum is onleesbaar: :date', ['date' => $date]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row  raw spreadsheet row
     * @return array{date: ?string, time: ?string, home_team: string, away_team: string, venue: string, court: string, city: string, venue_code: string, region: string, pool: string, code: string, type: string}
     */
    public function normalize(array $row): array
    {
        $home = trim((string) $row['Team thuis']);

        return [
            'date' => CellValue::date($row['Datum'] ?? null),
            'time' => CellValue::time($row['Tijd'] ?? null),
            'home_team' => $home,
            'away_team' => trim((string) $row['Team uit']),
            'venue' => trim((string) $row['Zaal']),
            'court' => trim((string) $row['Veld']),
            'city' => trim((string) ($row['Plaats'] ?? '')),
            'venue_code' => trim((string) ($row['Zaalcode'] ?? '')),
            'region' => trim((string) ($row['Regio'] ?? '')),
            'pool' => trim((string) ($row['Poule'] ?? '')),
            'code' => trim((string) $row['Code']),
            // A match is ours at home when the home team carries the club name.
            'type' => (ClubTeamMatcher::forCurrentClub()->startsWithClubName($home) ? MatchType::Home : MatchType::Away)->value,
        ];
    }

    /**
     * Whether the match date lies before today. A match of today still counts:
     * it can still be moved and so belongs in the planning.
     */
    public function isPlayed(?string $date): bool
    {
        return $date !== null && $date < CarbonImmutable::today()->toDateString();
    }
}
