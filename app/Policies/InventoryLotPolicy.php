<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryLot;

class InventoryLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.stock.view')
            || $user->hasPermission('inventory.moves.view')
            || $user->hasPermission('inventory.moves.manage');
    }

    public function view(User $user, InventoryLot $lot): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($lot);
    }
}
