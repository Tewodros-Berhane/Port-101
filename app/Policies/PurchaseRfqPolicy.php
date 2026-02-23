<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Purchasing\Models\PurchaseRfq;

class PurchaseRfqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchasing.rfq.view');
    }

    public function view(User $user, PurchaseRfq $rfq): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($rfq);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchasing.rfq.manage');
    }

    public function update(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchasing.rfq.manage')
            && $user->canAccessDataScopedRecord($rfq)
            && $rfq->status === PurchaseRfq::STATUS_DRAFT;
    }

    public function delete(User $user, PurchaseRfq $rfq): bool
    {
        return $this->update($user, $rfq);
    }

    public function send(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchasing.rfq.manage')
            && $user->canAccessDataScopedRecord($rfq)
            && $rfq->status === PurchaseRfq::STATUS_DRAFT;
    }

    public function markVendorResponded(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchasing.rfq.manage')
            && $user->canAccessDataScopedRecord($rfq)
            && in_array($rfq->status, [
                PurchaseRfq::STATUS_DRAFT,
                PurchaseRfq::STATUS_SENT,
            ], true);
    }

    public function select(User $user, PurchaseRfq $rfq): bool
    {
        return $user->hasPermission('purchasing.rfq.manage')
            && $user->canAccessDataScopedRecord($rfq)
            && in_array($rfq->status, [
                PurchaseRfq::STATUS_SENT,
                PurchaseRfq::STATUS_VENDOR_RESPONDED,
            ], true);
    }
}
