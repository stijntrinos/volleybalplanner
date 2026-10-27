<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\Duties\DutyPlanner;
use App\Planner\Services\Duties\PlanningContext;
use App\Planner\Services\Duties\PlayingRounds;
use App\Planner\Support\TeamCode;
use Carbon\CarbonImmutable;

/**
 * The team choice for one duty in the draft. The list is computed again on
 * opening, with the standing of the draft as it is on the screen, and
 * follows the sort order of the planning rules.
 */
trait ManagesTeamChoice
{
    /** @var array{title: string, description: ?string, path: string, current: ?string, candidates: list<array<string, mixed>>, balance: ?array<string, mixed>}|null */
    public ?array $teamChoice = null;

    /** @param 'match'|'hall_duty' $kind */
    public function openTeamChoice(string $kind, int $index, string $field = 'team_code'): void
    {
        $kind = $kind === 'hall_duty' ? 'hall_duty' : 'match';
        $field = $kind === 'hall_duty' ? 'team_code' : ($field === PlanningContext::DUTY_REFEREE ? PlanningContext::DUTY_REFEREE : PlanningContext::DUTY_SCORER);
        $list = $kind === 'hall_duty' ? 'hall_duties' : 'matches';
        $row = $this->draft[$list][$index] ?? null;
        if ($row === null || !$this->versionId) {
            return;
        }

        $candidates = app(DutyPlanner::class)->teamChoice(ScheduleVersion::findOrFail($this->versionId), $this->draft, $kind, $index, $field);
        $date = CarbonImmutable::parse((string) $row['date'])->translatedFormat('l j F');
        $current = filled($row[$field] ?? null) ? (string) $row[$field] : null;

        $this->teamChoice = [
            'title' => match ($field) {
                PlanningContext::DUTY_REFEREE => __('Fluitdienst :date, :time', ['date' => $date, 'time' => ($row['time'] ?? '').' - '.$this->displayEndTime((string) ($row['time'] ?? ''))]),
                PlanningContext::DUTY_SCORER => __('Teldienst :date, :time', ['date' => $date, 'time' => ($row['time'] ?? '').' - '.$this->displayEndTime((string) ($row['time'] ?? ''))]),
                default => __('Zaaldienst :date, :time', ['date' => $date, 'time' => ($row['start'] ?? '').' - '.($row['end'] ?? '')]),
            },
            'description' => $kind === 'hall_duty'
                ? ($row['venue'] ?? null)
                : trim(($row['home'] ?? '').' - '.($row['away'] ?? '')).(($row['venue'] ?? null) ? ' · '.$row['venue'] : ''),
            'path' => "draft.{$list}.{$index}.{$field}",
            'current' => $current,
            'candidates' => $candidates,
            'balance' => $this->balanceOfCurrentTeam($candidates, $current),
        ];
        $this->dispatch('open-modal', id: 'duty-team-choice');
    }

    /**
     * The end time of a match as members see it: start plus the match
     * duration from the season settings. Display only; the rules use the
     * duration of the match itself.
     */
    public function displayEndTime(string $start): string
    {
        if (!preg_match('/^\d{2}:\d{2}/', $start)) {
            return '';
        }

        $duration = once(fn (): int => (int) (PlannerSetting::current()->display_match_duration ?: 120));

        return PlayingRounds::time(PlayingRounds::minutes($start) + $duration);
    }

    /**
     * The choice comes in as a position in the list, not as a team code: a
     * code is free input and does not belong in a JavaScript attribute.
     */
    public function chooseTeam(int $index): void
    {
        $code = $this->teamChoice['candidates'][$index]['code'] ?? null;
        if ($code === null) {
            return;
        }

        data_set($this, $this->teamChoice['path'], $code);
        $this->updatedDraft();
        $this->teamChoice = null;
        $this->dispatch('close-modal', id: 'duty-team-choice');
    }

    /**
     * What moving means for the team that has the duty now: the standing
     * with the duty (before) and without (after), measured against the
     * minimum.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @return array{code: string, before: ?float, after: ?float, before_remaining: int, after_remaining: int}|null
     */
    private function balanceOfCurrentTeam(array $candidates, ?string $current): ?array
    {
        $candidate = collect($candidates)->first(fn (array $row): bool => TeamCode::equals($row['code'], $current));
        if ($candidate === null || !$candidate['has_minimum']) {
            return null;
        }

        return [
            'code' => $candidate['code'],
            'before' => $candidate['after_percentage'],
            'after' => $candidate['percentage'],
            'before_remaining' => $candidate['after_remaining'],
            'after_remaining' => $candidate['remaining'],
        ];
    }
}
