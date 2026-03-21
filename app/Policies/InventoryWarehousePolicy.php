<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryWarehouse;

class InventoryWarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.stock.view')
            || $user->hasPermission('inventory.moves.view');
    }

    public function view(User $user, InventoryWarehouse $warehouse): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($warehouse);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.moves.manage');
    }

    public function update(User $user, InventoryWarehouse $warehouse): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($warehouse);
    }

    public function delete(User $user, InventoryWarehouse $warehouse): bool
    {
        return $this->update($user, $warehouse);
    }
}
