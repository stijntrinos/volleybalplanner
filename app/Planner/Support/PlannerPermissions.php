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
        'DutiesDashboard', 'Exports',
    ];

    /** @var list<string> */
    public const WIDGETS = [
        'PendingChanges', 'PendingAwayChanges', 'NewChangeRequests', 'VersionBanner', 'PlannerStats', 'DashboardAside',
        'UpcomingMatchDays',
    ];

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
