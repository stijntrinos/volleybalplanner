<?php

namespace App\Planner\Support;

/**
 * Every Shield permission the planner uses. Roles are global; planner:sync-roles
 * creates these permissions and grants them to the club roles.
 */
final class PlannerPermissions
{
    public const ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    /** Shield model names. @var list<string> */
    public const MODELS = [
        'Team', 'Venue', 'Court', 'VenueAlias', 'TeamSource', 'ScheduleVersion', 'Availability', 'CalendarEvent',
        'ActivityColor', 'Training', 'Activity', 'HallDuty', 'VolleystarsBlock', 'VolleystarsTask', 'FixtureMatch',
        'PlannerSetting', 'NevoboChange', 'ChangeRequest',
    ];

    /** @var list<string> */
    public const PAGES = [
        'HomeMatches', 'AwayMatches', 'DutyPlanner', 'ImportPlanning', 'NevoboChanges', 'Settings', 'VenueOccupancy',
        'DutiesDashboard', 'Exports', 'NevoboLink',
    ];

    /** @var list<string> */
    public const WIDGETS = [
        'PendingChanges', 'PendingAwayChanges', 'NewChangeRequests', 'VersionBanner', 'PlannerStats', 'DashboardAside',
        'UpcomingMatchDays',
    ];

    /**
     * What a club admin can switch on or off for the planning committee.
     * Each group holds the models (every action) and pages it covers; the
     * dashboard widgets are always granted.
     *
     * @var array<string, array{models: list<string>, pages: list<string>}>
     */
    public const GROUPS = [
        'planning' => ['models' => ['FixtureMatch', 'Activity', 'ActivityColor'], 'pages' => ['HomeMatches', 'AwayMatches', 'VenueOccupancy']],
        'duties' => ['models' => ['HallDuty', 'VolleystarsBlock', 'VolleystarsTask'], 'pages' => ['DutyPlanner', 'DutiesDashboard']],
        'versions' => ['models' => ['ScheduleVersion'], 'pages' => []],
        'imports' => ['models' => ['NevoboChange', 'TeamSource'], 'pages' => ['ImportPlanning', 'NevoboChanges']],
        'requests' => ['models' => ['ChangeRequest'], 'pages' => []],
        'master_data' => ['models' => ['Team', 'Venue', 'Court', 'VenueAlias', 'Availability', 'CalendarEvent', 'Training'], 'pages' => ['NevoboLink']],
        'settings' => ['models' => ['PlannerSetting'], 'pages' => ['Settings']],
        'exports' => ['models' => [], 'pages' => ['Exports']],
    ];

    /** @return list<string> group keys */
    public static function groups(): array
    {
        return array_keys(self::GROUPS);
    }

    /**
     * The permissions for the given groups, plus the dashboard widgets.
     *
     * @param  list<string>  $groups
     * @return list<string>
     */
    public static function forGroups(array $groups): array
    {
        $permissions = array_map(fn (string $widget): string => "View:{$widget}", self::WIDGETS);

        foreach (array_intersect_key(self::GROUPS, array_flip($groups)) as $group) {
            foreach ($group['models'] as $model) {
                foreach (self::ACTIONS as $action) {
                    $permissions[] = "{$action}:{$model}";
                }
            }
            foreach ($group['pages'] as $page) {
                $permissions[] = "View:{$page}";
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @return list<string> */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::MODELS as $model) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = "{$action}:{$model}";
            }
        }

        foreach ([...self::PAGES, ...self::WIDGETS] as $component) {
            $permissions[] = "View:{$component}";
        }

        return $permissions;
    }
}
