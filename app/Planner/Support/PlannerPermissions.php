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
    public const MODELS = [];

    /** @var list<string> */
    public const PAGES = [];

    /** @var list<string> */
    public const WIDGETS = [];

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
