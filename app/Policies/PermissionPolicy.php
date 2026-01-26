<?php

namespace App\Policies;

use App\Core\RBAC\Models\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.permissions.view');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermission('core.permissions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.permissions.manage');
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermission('core.permissions.manage');
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermission('core.permissions.manage');
    }
}
