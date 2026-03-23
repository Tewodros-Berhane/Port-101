<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\InventoryCycleCount;

class InventoryCycleCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.counts.view')
            || $user->hasPermission('inventory.counts.manage');
    }

    public function view(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($cycleCount);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.counts.manage');
    }

    public function update(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $user->hasPermission('inventory.counts.manage')
            && $user->canAccessDataScopedRecord($cycleCount)
            && in_array($cycleCount->status, [
                InventoryCycleCount::STATUS_DRAFT,
                InventoryCycleCount::STATUS_IN_PROGRESS,
                InventoryCycleCount::STATUS_REVIEWED,
            ], true)
            && $cycleCount->status !== InventoryCycleCount::STATUS_POSTED;
    }

    public function start(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $this->update($user, $cycleCount)
            && $cycleCount->status === InventoryCycleCount::STATUS_DRAFT;
    }

    public function review(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $this->update($user, $cycleCount)
            && in_array($cycleCount->status, [
                InventoryCycleCount::STATUS_DRAFT,
                InventoryCycleCount::STATUS_IN_PROGRESS,
                InventoryCycleCount::STATUS_REVIEWED,
            ], true);
    }

    public function post(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $user->hasPermission('inventory.counts.manage')
            && $user->canAccessDataScopedRecord($cycleCount)
            && $cycleCount->status === InventoryCycleCount::STATUS_REVIEWED
            && (
                ! $cycleCount->requires_approval
                || $cycleCount->approval_status === InventoryCycleCount::APPROVAL_STATUS_APPROVED
            );
    }

    public function cancel(User $user, InventoryCycleCount $cycleCount): bool
    {
        return $this->update($user, $cycleCount)
            && $cycleCount->status !== InventoryCycleCount::STATUS_POSTED
            && $cycleCount->status !== InventoryCycleCount::STATUS_CANCELLED;
    }
}
