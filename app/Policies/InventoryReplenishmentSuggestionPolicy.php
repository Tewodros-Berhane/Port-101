<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryReplenishmentSuggestion;

class InventoryReplenishmentSuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.reordering.view')
            || $user->hasPermission('inventory.reordering.manage');
    }

    public function view(User $user, InventoryReplenishmentSuggestion $suggestion): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($suggestion);
    }

    public function dismiss(User $user, InventoryReplenishmentSuggestion $suggestion): bool
    {
        return $user->hasPermission('inventory.reordering.manage')
            && $user->canAccessDataScopedRecord($suggestion)
            && $suggestion->status === InventoryReplenishmentSuggestion::STATUS_OPEN;
    }

    public function convertToRfq(User $user, InventoryReplenishmentSuggestion $suggestion): bool
    {
        return $user->hasPermission('inventory.reordering.manage')
            && $user->hasPermission('purchasing.rfq.manage')
            && $user->canAccessDataScopedRecord($suggestion)
            && $suggestion->status === InventoryReplenishmentSuggestion::STATUS_OPEN;
    }
}
