<?php

namespace App\Planner\Services\Duties;

use App\Planner\Models\Team;

/**
 * The standing of a team against its minimum. The need only serves to
 * compute the minimum; every comparison after that uses the minimum. None of
 * this is stored: the standing always follows from the entered values and
 * the duties.
 */
final class PointsBalance
{
    public function __construct(
        public readonly int $need,
        public readonly ?int $minimumPercentage,
        public readonly int $earned,
        public readonly int $planned = 0,
        public readonly bool $included = true,
    ) {}

    /**
     * Without the volunteer points module a team has no need and nothing
     * earned outside the planning: only the planned duties count.
     */
    public static function forTeam(Team $team, int $planned = 0, bool $withPoints = true): self
    {
        return new self(
            need: $withPoints ? (int) $team->volunteer_points_used : 0,
            minimumPercentage: $team->volunteer_points_percentage,
            earned: $withPoints ? (int) $team->volunteer_points_earned : 0,
            planned: $planned,
            included: $withPoints && (bool) ($team->include_volunteers ?? true),
        );
    }

    /**
     * Need times minimum percentage, rounded to whole points with half a point
     * up. An empty percentage means 100%.
     */
    public static function computeMinimum(int $need, ?int $minimumPercentage): int
    {
        return intdiv(max(0, $need) * ($minimumPercentage ?? 100) + 50, 100);
    }

    public function withPlanned(int $planned): self
    {
        return new self($this->need, $this->minimumPercentage, $this->earned, $planned, $this->included);
    }

    public function minimum(): int
    {
        return $this->included ? self::computeMinimum($this->need, $this->minimumPercentage) : 0;
    }

    /** A team without a minimum need not do anything and comes last. */
    public function hasMinimum(): bool
    {
        return $this->minimum() > 0;
    }

    public function total(): int
    {
        return $this->earned + $this->planned;
    }

    public function remaining(): int
    {
        return max(0, $this->minimum() - $this->total());
    }

    public function aboveMinimum(): int
    {
        return max(0, $this->total() - $this->minimum());
    }

    /** Total earned as a percentage of the minimum, to one decimal; null without a minimum. */
    public function percentage(): ?float
    {
        return $this->hasMinimum() ? round($this->total() / $this->minimum() * 100, 1) : null;
    }

    public function isBelowMinimum(): bool
    {
        return $this->hasMinimum() && $this->total() < $this->minimum();
    }

    public function isAboveTarget(): bool
    {
        return $this->hasMinimum() && $this->total() > $this->minimum();
    }

    /** @return array{need: int, minimum_percentage: ?int, minimum: int, earned: int, planned: int, total: int, remaining: int, above_minimum: int, percentage: ?float, has_minimum: bool} */
    public function toArray(): array
    {
        return [
            'need' => $this->need,
            'minimum_percentage' => $this->minimumPercentage,
            'minimum' => $this->minimum(),
            'earned' => $this->earned,
            'planned' => $this->planned,
            'total' => $this->total(),
            'remaining' => $this->remaining(),
            'above_minimum' => $this->aboveMinimum(),
            'percentage' => $this->percentage(),
            'has_minimum' => $this->hasMinimum(),
        ];
    }

    /** "72,0%", or a dash without a minimum. */
    public static function percentageText(?float $percentage): string
    {
        return $percentage === null ? '-' : number_format($percentage, 1, ',', '.').'%';
    }
}
