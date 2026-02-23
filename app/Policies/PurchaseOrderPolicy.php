<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchasing.po.view');
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($order);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchasing.po.manage');
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchasing.po.manage')
            && $user->canAccessDataScopedRecord($order)
            && $order->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return $this->update($user, $order);
    }

    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchasing.po.approve')
            && $user->canAccessDataScopedRecord($order)
            && $order->status === PurchaseOrder::STATUS_DRAFT;
    }

    public function place(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchasing.po.manage')
            && $user->canAccessDataScopedRecord($order)
            && in_array($order->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_APPROVED,
            ], true);
    }

    public function receive(User $user, PurchaseOrder $order): bool
    {
        return $user->hasPermission('purchasing.po.manage')
            && $user->canAccessDataScopedRecord($order)
            && in_array($order->status, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ], true);
    }
}
