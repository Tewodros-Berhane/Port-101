<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryReorderRule;

class InventoryReorderRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.reordering.view')
            || $user->hasPermission('inventory.reordering.manage');
    }

    public function view(User $user, InventoryReorderRule $rule): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($rule);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.reordering.manage');
    }

    public function update(User $user, InventoryReorderRule $rule): bool
    {
        return $user->hasPermission('inventory.reordering.manage')
            && $user->canAccessDataScopedRecord($rule);
    }

    public function delete(User $user, InventoryReorderRule $rule): bool
    {
        return $this->update($user, $rule);
    }

    public function scan(User $user): bool
    {
        return $user->hasPermission('inventory.reordering.manage');
    }
}
