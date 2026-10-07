<?php

namespace App\Planner\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class PlannerPolicy
{
    /**
     * Model name as used in the Shield permission names, e.g. 'Team' for
     * permissions like 'ViewAny:Team'.
     */
    abstract protected function permissionModel(): string;

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:'.$this->permissionModel());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('View:'.$this->permissionModel());
    }

    public function create(User $user): bool
    {
        return $user->can('Create:'.$this->permissionModel());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('Update:'.$this->permissionModel());
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('Delete:'.$this->permissionModel());
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:'.$this->permissionModel());
    }
}
