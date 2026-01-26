<?php

namespace App\Policies;

use App\Core\RBAC\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('core.roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('core.roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('core.roles.manage');
    }
}
