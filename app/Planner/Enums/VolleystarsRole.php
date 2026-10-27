<?php

namespace App\Planner\Enums;

use App\Planner\Models\VolleystarsBlock;
use Filament\Support\Contracts\HasLabel;

/** Task within a VolleyStars block. */
enum VolleystarsRole: string implements HasLabel
{
    case Setup = 'setup';
    case Teardown = 'teardown';
    case Scoring = 'scoring';
    case Refereeing = 'refereeing';

    public function getLabel(): string
    {
        return match ($this) {
            self::Setup => __('Opbouwen'),
            self::Teardown => __('Afbouwen'),
            self::Scoring => __('Tellen'),
            self::Refereeing => __('Fluiten'),
        };
    }

    /**
     * The roles in the order of the morning.
     *
     * @return list<self>
     */
    public static function inDayOrder(): array
    {
        return [self::Setup, self::Scoring, self::Refereeing, self::Teardown];
    }

    /** @return array<string, string> value => label, in the order of the morning */
    public static function options(): array
    {
        return collect(self::inDayOrder())->mapWithKeys(fn (self $role): array => [$role->value => $role->getLabel()])->all();
    }

    /**
     * The time slot of this role within the block: [start, end].
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function times(VolleystarsBlock $block): array
    {
        return match ($this) {
            self::Scoring => [$block->scoring_start, $block->scoring_end],
            self::Refereeing => [$block->refereeing_start ?? $block->scoring_start, $block->refereeing_end ?? $block->scoring_end],
            self::Teardown => [$block->teardown_start, $block->teardown_end],
            self::Setup => [$block->setup_start, $block->setup_end],
        };
    }
}
