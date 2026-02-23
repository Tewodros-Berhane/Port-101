<?php

namespace App\Policies;

use App\Modules\Inventory\Models\InventoryLocation;
use App\Models\User;

class InventoryLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.stock.view')
            || $user->hasPermission('inventory.moves.view');
    }

    public function view(User $user, InventoryLocation $location): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($location);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.moves.manage');
    }

    public function update(User $user, InventoryLocation $location): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($location);
    }

    public function delete(User $user, InventoryLocation $location): bool
    {
        return $this->update($user, $location);
    }
}


