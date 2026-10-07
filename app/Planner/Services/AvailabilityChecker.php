<?php

namespace App\Planner\Services;

use App\Planner\Models\FixtureMatch;
use App\Planner\Models\PlannerSetting;
use App\Planner\Models\TeamSource;
use App\Planner\Support\MatchCode;
use Illuminate\Support\Collection;

/**
 * The traffic light for one placement: the conflict engine's findings plus the
 * team's external matches from its Nevobo sources.
 */
class AvailabilityChecker
{
    public const RESULT_GREEN = 'green';

    public const RESULT_WARNING = 'warning';

    public const RESULT_RED = 'red';

    public function __construct(
        private readonly ConflictEngine $conflicts,
        private readonly NevoboRssService $rss,
    ) {}

    /**
     * @param  array<string, mixed>  $placement  date, time, duration_minutes, venue_id, court_id, second_court_id
     * @param  Collection<int, non-empty-array<string, mixed>>|null  $externalMatches  as returned by externalMatches()
     * @return array{result: string, reasons: list<array<string, mixed>>, can_proceed: bool}
     */
    public function check(FixtureMatch $match, array $placement, ?Collection $externalMatches = null, bool $log = true): array
    {
        $reasons = $this->conflicts->checkPlacement($match, $placement);
        $settings = PlannerSetting::current();
        $externalMatches ??= $this->externalMatches($match);
        $sameDay = $match->date?->toDateString() === ($placement['date'] ?? null);

        foreach ($externalMatches as $external) {
            if (($external['error'] ?? false) === true) {
                $reasons[] = [
                    'level' => ConflictEngine::LEVEL_WARNING,
                    'type' => ConflictEngine::TYPE_EXTERNAL_SOURCE,
                    'message' => $external['message'] ?? __('Een externe bron kon niet worden gecontroleerd.'),
                ];

                continue;
            }
            if (($external['date'] ?? null) !== $placement['date']) {
                continue;
            }
            if ($sameDay) {
                continue;
            }
            if ($this->isSameMatch($match, $external)) {
                continue;
            }
            $teamIds = array_filter([$match->home_team_id, $match->away_team_id]);
            if (in_array($external['team_id'] ?? null, $teamIds, true)) {
                $reasons[] = [
                    'level' => ConflictEngine::LEVEL_CONFLICT,
                    'type' => ConflictEngine::TYPE_EXTERNAL_TEAM,
                    'message' => __('Het team heeft op deze dag al een externe wedstrijd.'),
                ];
            }
        }

        $reasons = collect($reasons)->unique(fn (array $reason): string => implode('|', [
            $reason['level'], $reason['type'], (string) $reason['message'],
        ]))->values()->all();
        $hasConflict = collect($reasons)->contains('level', ConflictEngine::LEVEL_CONFLICT);
        $hasWarning = collect($reasons)->contains('level', ConflictEngine::LEVEL_WARNING);
        $result = $hasConflict ? self::RESULT_RED : ($hasWarning ? self::RESULT_WARNING : self::RESULT_GREEN);

        // Only single checks requested by a person belong in the audit log. A
        // proposal finder does dozens per click and would fill the log with
        // machine checks.
        if ($log) {
            activity('planner-availability-check')
                ->performedOn($match)
                ->withProperties(['placement' => $placement, 'result' => $result, 'reasons' => $reasons])
                ->log('Availability checked');
        }

        return [
            'result' => $result,
            'reasons' => $reasons,
            'can_proceed' => !$hasConflict && (!$hasWarning || $settings->admin_override_external_error),
        ];
    }

    /**
     * The matches in the active Nevobo sources of both teams. A source that
     * could not be read gives one entry with error => true.
     *
     * @return Collection<int, non-empty-array<string, mixed>> NevoboRssService::parse() items plus team_id and source_id,
     *                                                         or ['error' => true, 'team_id' => int, 'message' => string]
     */
    public function externalMatches(FixtureMatch $match): Collection
    {
        $teamIds = array_values(array_filter([$match->home_team_id, $match->away_team_id]));
        if ($teamIds === []) {
            return collect();
        }

        return TeamSource::query()
            ->whereIn('team_id', $teamIds)
            ->where('is_active', true)
            ->get()
            ->flatMap(function (TeamSource $source): array {
                $result = $this->rss->fetch($source);
                if (!$result['ok']) {
                    return [[
                        'error' => true,
                        'team_id' => $source->team_id,
                        'message' => __('Bron :name kon niet worden gecontroleerd: :reason', [
                            'name' => $source->name, 'reason' => $result['reason'],
                        ]),
                    ]];
                }

                return collect($result['items'])
                    ->map(fn (array $item): array => $item + ['team_id' => $source->team_id, 'source_id' => $source->id])
                    ->all();
            });
    }

    /** @param array<string, mixed> $external */
    private function isSameMatch(FixtureMatch $match, array $external): bool
    {
        $code = static fn (?string $value): string => MatchCode::key($value);
        if ($code($match->match_code) !== '' && $code($match->match_code) === $code($external['code'] ?? null)) {
            return true;
        }

        $team = fn (?string $value): string => preg_replace('/\s+/', ' ', mb_strtoupper(trim($value ?? '')));

        return $team($match->home_team_name) !== ''
            && $team($match->home_team_name) === $team($external['home_team'] ?? null)
            && $team($match->away_team_name) === $team($external['away_team'] ?? null);
    }
}
