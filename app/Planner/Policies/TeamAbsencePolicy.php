<?php

namespace App\Planner\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * An absence belongs to the team: whoever may edit the team may also add,
 * change and remove its absences.
 */
class TeamAbsencePolicy extends PlannerPolicy
{
    protected function permissionModel(): string
    {
        return 'Team';
    }

    public function create(User $user): bool
    {
        return $user->can('Update:Team');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('Update:Team');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('Update:Team');
    }
}
