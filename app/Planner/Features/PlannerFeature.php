<?php

namespace App\Planner\Features;

use Filament\Support\Contracts\HasLabel;

/**
 * Optional planner modules that can be switched on per club. Everything not
 * listed here is core and always available.
 */
enum PlannerFeature: string implements HasLabel
{
    case AwayMatches = 'away_matches';
    case Duties = 'duties';
    case VolunteerPoints = 'volunteer_points';
    case Volleystars = 'volleystars';
    case Trainings = 'trainings';
    case ChangeRequests = 'change_requests';
    case LockerRooms = 'locker_rooms';
    case SittingVolleyball = 'sitting_volleyball';
    case RssFeeds = 'rss_feeds';

    /** @return list<self> */
    public function requires(): array
    {
        return match ($this) {
            self::VolunteerPoints, self::Volleystars => [self::Duties],
            default => [],
        };
    }

    public function getLabel(): string
    {
        return __('planner.features.'.$this->value);
    }

    /** What the module adds, shown when switching it on for a club. */
    public function getDescription(): string
    {
        return __('planner.feature_descriptions.'.$this->value);
    }

    /**
     * One message per selected feature whose requirement is not selected.
     *
     * @param  array<int, string>  $values
     * @return list<string>
     */
    public static function unmetRequirements(array $values): array
    {
        $unmet = [];

        foreach ($values as $value) {
            $feature = self::tryFrom($value);

            foreach ($feature?->requires() ?? [] as $required) {
                if (!in_array($required->value, $values, true)) {
                    $unmet[] = __('planner.feature_requires', [
                        'feature' => $feature->getLabel(),
                        'required' => $required->getLabel(),
                    ]);
                }
            }
        }

        return $unmet;
    }

    /** @return list<string> */
    public static function allValues(): array
    {
        return array_map(fn (self $feature): string => $feature->value, self::cases());
    }
}
