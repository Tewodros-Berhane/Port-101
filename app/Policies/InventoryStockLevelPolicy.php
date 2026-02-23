<?php

namespace App\Policies;

use App\Core\Inventory\Models\InventoryStockLevel;
use App\Models\User;

class InventoryStockLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.stock.view');
    }

    public function view(User $user, InventoryStockLevel $level): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($level);
    }
}
