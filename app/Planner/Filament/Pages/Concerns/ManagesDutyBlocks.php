<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\PlannerSetting;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Services\Duties\DraftOverview;
use App\Planner\Services\Duties\DutyPlanner;
use DomainException;
use Filament\Notifications\Notification;

/**
 * The actions per playing day and venue on the duty planner: check,
 * regenerate, apply and lock an assignment.
 */
trait ManagesDutyBlocks
{
    /** @return list<array<string, mixed>> */
    public function getBlocksProperty(): array
    {
        return app(DraftOverview::class)->blocks($this->draft, $this->validation, $this->mainVenueId());
    }

    /** The venue that comes first per playing day; set in the planner settings. */
    private function mainVenueId(): ?int
    {
        return PlannerSetting::current()->main_venue_id;
    }

    public function lock(int $index, string $field): void
    {
        if (!isset($this->draft['matches'][$index]) || !in_array($field, ['referee', 'scorer'], true)) {
            return;
        }

        $this->draft['matches'][$index]['locked'][$field] = !($this->draft['matches'][$index]['locked'][$field] ?? false);
    }

    public function lockHallDuty(int $index): void
    {
        if (!isset($this->draft['hall_duties'][$index])) {
            return;
        }

        $this->draft['hall_duties'][$index]['locked'] = !($this->draft['hall_duties'][$index]['locked'] ?? false);
    }

    public function lockDay(string $date): void
    {
        $this->lockFilledRows($date);

        Notification::make()
            ->title(__('Ingevulde taken van deze dag zijn vastgezet'))
            ->body(__('Lege velden blijven vrij om later automatisch aan te vullen.'))
            ->success()
            ->send();
    }

    /**
     * Toggles a playing day as hall duty day. Clearing a duty did not stick:
     * saving skips a duty without a team, so the next generation proposed a
     * team again. The exception therefore lives in the planner settings, where
     * the day planner reads it too.
     */
    public function toggleHallDutyDay(string $date): void
    {
        $this->requireMatchPermission('Update');
        $settings = PlannerSetting::current();

        $exceptions = $settings->hallDutyExceptions();
        $enable = in_array($date, $exceptions, true);
        $settings->update(['hall_duty_exceptions' => $enable
            ? array_values(array_diff($exceptions, [$date]))
            : [...$exceptions, $date]]);

        $this->draft['hall_duties'] = $enable
            ? $this->draft['hall_duties'] ?? []
            : array_values(array_filter(
                $this->draft['hall_duties'] ?? [],
                fn (array $row): bool => ($row['date'] ?? null) !== $date,
            ));
        $this->validation = [];
        $this->checkedAt = null;

        Notification::make()
            ->title($enable ? __('Weer een zaaldienstdag') : __('Geen zaaldienst op deze dag'))
            ->body($enable
                ? __('Genereer opnieuw om een team voor te stellen.')
                : __('De planner stelt hier geen team meer voor, ook niet bij opnieuw genereren.'))
            ->success()
            ->send();
    }

    /** Whether this playing day is marked as an exception (no hall duty). */
    public function isHallDutyException(string $date): bool
    {
        return PlannerSetting::current()->isHallDutyException($date);
    }

    public function generateBlock(string $date, ?int $venueId): void
    {
        $fresh = app(DutyPlanner::class)->generate(
            ScheduleVersion::findOrFail($this->versionId), DutyPlanner::MODE_REDISTRIBUTE, $date, $date, $venueId,
        );

        $this->draft['matches'] = $this->replaceRows(
            $this->draft['matches'] ?? [], $fresh['matches'] ?? [], $date, $venueId,
        );
        $this->draft['hall_duties'] = $this->replaceRows(
            $this->draft['hall_duties'] ?? [], $fresh['hall_duties'] ?? [], $date, $venueId,
        );
        $this->validation = [];
        $this->checkedAt = null;

        Notification::make()->title(__('Blok opnieuw gegenereerd'))->success()->send();
    }

    public function checkBlock(string $date, ?int $venueId): void
    {
        // Only this block: validating the whole draft put errors of other
        // playing days in the Livewire state, so those warnings suddenly
        // showed up everywhere after applying one block.
        $this->validation = app(DutyPlanner::class)->validate(
            ScheduleVersion::findOrFail($this->versionId),
            $this->draft,
            $date,
            $venueId,
            singleVenue: true,
        );
        $this->checkedAt = now()->format('d-m-Y H:i');
        $issues = (int) ($this->findBlock($date, $venueId)['counts']['issues'] ?? 0);

        Notification::make()
            ->title($issues
                ? trans_choice(':count aandachtspunt in dit blok|:count aandachtspunten in dit blok', $issues)
                : __('Dit blok is helemaal geldig'))
            ->color($issues ? 'warning' : 'success')
            ->send();
    }

    public function applyBlock(string $date, ?int $venueId): void
    {
        $this->requireMatchPermission('Update');
        $this->checkBlock($date, $venueId);
        if (($this->findBlock($date, $venueId)['counts']['issues'] ?? 0) > 0) {
            $this->showError(__('Blok niet doorgevoerd'), __('Los eerst de ongeldige toewijzingen in dit blok op.'));

            return;
        }

        try {
            $version = $this->editableVersion();
        } catch (DomainException $exception) {
            $this->showError(__('Blok niet doorgevoerd'), $exception->getMessage());

            return;
        }

        $this->lockRowsInBlock($date, $venueId);

        app(DutyPlanner::class)->save($version, [
            'matches' => $this->rowsInBlock($this->draft['matches'] ?? [], $date, $venueId),
            'hall_duties' => $this->rowsInBlock($this->draft['hall_duties'] ?? [], $date, $venueId),
        ], auth()->id(), $date, $date, $venueId, singleVenue: true);

        Notification::make()->title(__('Blok toegepast op de planning'))->success()->send();
    }

    /** @return array<string, mixed>|null */
    private function findBlock(string $date, ?int $venueId): ?array
    {
        return collect($this->getBlocksProperty())->first(
            fn (array $block): bool => $block['date'] === $date && $block['venue_id'] === $venueId,
        );
    }

    /**
     * Replaces the rows of one block with freshly generated rows. What the
     * user locked stays; that is what the lock is for.
     */
    private function replaceRows(array $existing, array $fresh, string $date, ?int $venueId): array
    {
        $replaced = $this->keepLockedRows($this->rowsInBlock($existing, $date, $venueId), $fresh);

        return collect($existing)
            ->reject(fn (array $row): bool => $this->inBlock($row, $date, $venueId))
            ->concat($replaced)
            ->values()
            ->all();
    }

    /**
     * Keeps a locked field without freezing the other duty of the same match,
     * so team settings still apply to every unlocked field on regeneration.
     */
    protected function keepLockedRows(array $existing, array $fresh): array
    {
        $oldRows = collect($existing)->keyBy(fn (array $row): string => $this->rowKey($row));

        return collect($fresh)->map(function (array $row) use ($oldRows): array {
            $old = $oldRows->get($this->rowKey($row));
            if (!$old) {
                return $row;
            }

            if (isset($row['id'])) {
                foreach (['referee', 'scorer'] as $field) {
                    $locked = (bool) ($old['locked'][$field] ?? false);
                    $row['locked'][$field] = $locked;
                    if ($locked) {
                        $row[$field] = $old[$field] ?? null;
                        $row['suggested_'.$field] = $old['suggested_'.$field] ?? ($old[$field] ?? null);
                    }
                }

                return $row;
            }

            $row['locked'] = (bool) ($old['locked'] ?? false);
            if ($row['locked']) {
                $row['team_code'] = $old['team_code'] ?? null;
                $row['suggested_team'] = $old['suggested_team'] ?? ($old['team_code'] ?? null);
            }

            return $row;
        })->values()->all();
    }

    /** Without a date the whole draft, with a date the whole playing day. */
    protected function lockFilledRows(?string $date = null): void
    {
        $this->lockRows(fn (array $row): bool => $date === null || ($row['date'] ?? null) === $date);
    }

    /** One block, so including the venue. */
    private function lockRowsInBlock(string $date, ?int $venueId): void
    {
        $this->lockRows(fn (array $row): bool => $this->inBlock($row, $date, $venueId));
    }

    /** @param callable(array<string, mixed>): bool $belongs */
    private function lockRows(callable $belongs): void
    {
        foreach ($this->draft['matches'] ?? [] as $index => $row) {
            if (!$belongs($row)) {
                continue;
            }

            foreach (['referee', 'scorer'] as $field) {
                $this->draft['matches'][$index]['locked'][$field] = filled($row[$field] ?? null);
            }
        }

        foreach ($this->draft['hall_duties'] ?? [] as $index => $row) {
            if (!$belongs($row)) {
                continue;
            }

            $this->draft['hall_duties'][$index]['locked'] = filled($row['team_code'] ?? null);
        }
    }

    private function rowsInBlock(array $rows, string $date, ?int $venueId): array
    {
        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->inBlock($row, $date, $venueId),
        ));
    }

    private function inBlock(array $row, string $date, ?int $venueId): bool
    {
        return ($row['date'] ?? null) === $date && ($row['venue_id'] ?? null) === $venueId;
    }

    private function rowKey(array $row): string
    {
        return isset($row['id'])
            ? 'm'.$row['id']
            : 'h'.($row['date'] ?? '').'|'.($row['venue_id'] ?? '').'|'.($row['start'] ?? '');
    }
}
