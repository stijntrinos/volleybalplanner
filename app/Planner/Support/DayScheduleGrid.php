<?php

namespace App\Planner\Support;

use App\Planner\Models\Court;
use Illuminate\Support\Collection;

/**
 * The layout of the day schedule: which card sits in which column and row.
 *
 * The schedule used to be a grid of hand-computed pixels -- column widths,
 * row heights and a distribution of the remaining space. From about nine
 * courts on that ran into negative sizes. Only row and column numbers come
 * out of here now; CSS Grid distributes the space.
 *
 * Row 1 is the column header, the start times begin at row 2. The hall duty
 * always sits in the last column.
 */
final class DayScheduleGrid
{
    /**
     * Matches starting up to this many minutes after the first one of a row
     * belong to that row. A shift of a quarter of an hour otherwise gave a row
     * per start time, with gaps around it. The same margin as the rounds of
     * the duty planner, so a hall duty starts on a row.
     */
    private const ROW_MARGIN = 20;

    /** @var Collection<int, string> the first start time of each row */
    public readonly Collection $times;

    /** @var array<int|string, int> the row (from 0) per match key */
    private array $rowPerMatch = [];

    /**
     * @param  Collection<int, Court>  $courts
     * @param  Collection<int, mixed>  $matches
     * @param  Collection<int, mixed>  $hallDuties
     */
    public function __construct(
        private readonly Collection $courts,
        private readonly Collection $matches,
        private readonly Collection $hallDuties,
    ) {
        $this->times = $this->assignRows();
    }

    /**
     * Walks the matches by time. A match joins the last row when it starts
     * within the margin and its courts are still free there.
     *
     * @return Collection<int, string>
     */
    private function assignRows(): Collection
    {
        $rows = [];
        $sorted = $this->matches
            ->map(fn ($match): ?string => DateFormat::time($match->time))
            ->filter(fn (?string $time): bool => $time !== null)
            ->sort();

        foreach ($sorted as $key => $time) {
            $match = $this->matches->get($key);
            $courts = array_filter([$match->court_id, $match->second_court_id]);
            $last = array_key_last($rows);
            $fits = $last !== null
                && $this->minutes($time) - $this->minutes($rows[$last]['time']) <= self::ROW_MARGIN
                && array_intersect($courts, $rows[$last]['courts']) === [];

            if (!$fits) {
                $rows[] = ['time' => $time, 'courts' => []];
                $last = array_key_last($rows);
            }
            $rows[$last]['courts'] = [...$rows[$last]['courts'], ...$courts];
            $this->rowPerMatch[$key] = $last;
        }

        return collect($rows)->pluck('time')->values();
    }

    private function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    public function columns(): int
    {
        return max(1, $this->courts->count());
    }

    public function hallDutyColumn(): int
    {
        return $this->columns() + 1;
    }

    public function rows(): int
    {
        return max(1, $this->times->count());
    }

    /** @return Collection<int, Court> */
    public function courts(): Collection
    {
        return $this->courts;
    }

    /**
     * The match cards with their place in the grid.
     *
     * A match on two courts gives one card spanning both columns; the second
     * court gets no card of its own in that row.
     *
     * @return Collection<int, array{match: mixed, column: int, column_span: int, row: int}>
     */
    public function cards(): Collection
    {
        $cards = [];
        foreach ($this->matches as $key => $match) {
            $column = $this->columnOf($match->court_id);
            if ($column === null) {
                continue;
            }
            $second = $this->columnOf($match->second_court_id);

            $cards[] = [
                'match' => $match,
                'column' => $second === null ? $column : min($column, $second),
                'column_span' => $second === null ? 1 : abs($second - $column) + 1,
                'row' => ($this->rowPerMatch[$key] ?? 0) + 2,
            ];
        }

        return collect($cards);
    }

    /**
     * The places in the grid without a match.
     *
     * Without these blocks the background shows through the grid and the
     * column seems to end; the design puts a pale block there. Empty places
     * next to each other in the same row become one block, otherwise an empty
     * evening shows three separate blocks side by side.
     *
     * @return Collection<int, array{column: int, column_span: int, row: int}>
     */
    public function emptyBlocks(): Collection
    {
        $occupied = $this->cards()->flatMap(function (array $card): array {
            return collect(range(0, $card['column_span'] - 1))
                ->map(fn (int $offset): string => ($card['column'] + $offset).':'.$card['row'])
                ->all();
        })->flip();

        return collect(range(2, $this->rows() + 1))
            ->flatMap(function (int $row) use ($occupied): array {
                $blocks = [];
                $column = 1;
                while ($column <= $this->columns()) {
                    if ($occupied->has($column.':'.$row)) {
                        $column++;

                        continue;
                    }
                    $span = 0;
                    while ($column + $span <= $this->columns() && !$occupied->has(($column + $span).':'.$row)) {
                        $span++;
                    }
                    $blocks[] = ['column' => $column, 'column_span' => $span, 'row' => $row];
                    $column += $span;
                }

                return $blocks;
            })->values();
    }

    /**
     * The hall duty blocks with their place in the last column.
     *
     * A duty covers the start times within its period. When the duty runs on
     * after the last match, the block stretches to the bottom.
     *
     * @return Collection<int, array{duty: mixed, row: int, row_span: int, start: string, end: string}>
     */
    public function hallDuties(): Collection
    {
        return $this->hallDuties->map(function ($duty): ?array {
            $start = (string) DateFormat::time($duty->start_time);
            $end = (string) DateFormat::time($duty->end_time);
            $firstRow = $this->times->search(fn (string $time): bool => $time >= $start);
            if ($firstRow === false) {
                return null;
            }
            $afterEnd = $this->times->search(fn (string $time): bool => $time >= $end);
            $span = $afterEnd === false ? $this->times->count() - $firstRow : $afterEnd - $firstRow;
            // A duty that ended before the first match starts covers no time
            // slot at all. It used to get a block of zero height.
            if ($span < 1) {
                return null;
            }

            return [
                'duty' => $duty,
                'row' => $firstRow + 2,
                'row_span' => $span,
                'start' => $start,
                'end' => $end,
            ];
        })->filter()->values();
    }

    private function columnOf(?int $courtId): ?int
    {
        if ($courtId === null) {
            return null;
        }
        $index = $this->courts->search(fn (Court $court): bool => $court->id === $courtId);

        return $index === false ? null : $index + 1;
    }
}
