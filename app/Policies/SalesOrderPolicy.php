<?php

namespace App\Policies;

use App\Modules\Sales\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.orders.view');
    }

    public function view(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.orders.view')
            && $user->canAccessDataScopedRecord($order);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.orders.manage');
    }

    public function update(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.orders.manage')
            && $user->canAccessDataScopedRecord($order)
            && $order->status === SalesOrder::STATUS_DRAFT;
    }

    public function delete(User $user, SalesOrder $order): bool
    {
        return $this->update($user, $order);
    }

    public function approve(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.orders.approve')
            && $user->canAccessDataScopedRecord($order)
            && $order->status === SalesOrder::STATUS_DRAFT;
    }

    public function confirm(User $user, SalesOrder $order): bool
    {
        return $user->hasPermission('sales.orders.manage')
            && $user->canAccessDataScopedRecord($order)
            && $order->status === SalesOrder::STATUS_DRAFT;
    }
}


