<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryStockMove;

class InventoryStockMovePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.moves.view')
            || $user->hasPermission('inventory.moves.manage');
    }

    public function view(User $user, InventoryStockMove $move): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($move);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.moves.manage');
    }

    public function update(User $user, InventoryStockMove $move): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($move)
            && $move->status === InventoryStockMove::STATUS_DRAFT;
    }

    public function delete(User $user, InventoryStockMove $move): bool
    {
        return $this->update($user, $move);
    }

    public function reserve(User $user, InventoryStockMove $move): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($move)
            && $move->status === InventoryStockMove::STATUS_DRAFT;
    }

    public function complete(User $user, InventoryStockMove $move): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($move)
            && in_array($move->status, [
                InventoryStockMove::STATUS_DRAFT,
                InventoryStockMove::STATUS_RESERVED,
            ], true);
    }

    public function cancel(User $user, InventoryStockMove $move): bool
    {
        return $user->hasPermission('inventory.moves.manage')
            && $user->canAccessDataScopedRecord($move)
            && in_array($move->status, [
                InventoryStockMove::STATUS_DRAFT,
                InventoryStockMove::STATUS_RESERVED,
            ], true);
    }
}
