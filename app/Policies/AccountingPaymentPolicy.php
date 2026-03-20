<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingPayment;

class AccountingPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.payments.view');
    }

    public function view(User $user, AccountingPayment $payment): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($payment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.payments.manage');
    }

    public function update(User $user, AccountingPayment $payment): bool
    {
        return $user->hasPermission('accounting.payments.manage')
            && $user->canAccessDataScopedRecord($payment)
            && $payment->status === AccountingPayment::STATUS_DRAFT;
    }

    public function delete(User $user, AccountingPayment $payment): bool
    {
        return $this->update($user, $payment);
    }

    public function post(User $user, AccountingPayment $payment): bool
    {
        return $user->hasPermission('accounting.payments.manage')
            && $user->canAccessDataScopedRecord($payment)
            && $payment->status === AccountingPayment::STATUS_DRAFT;
    }

    public function reconcile(User $user, AccountingPayment $payment): bool
    {
        return $user->hasPermission('accounting.payments.manage')
            && $user->canAccessDataScopedRecord($payment)
            && $payment->status === AccountingPayment::STATUS_POSTED;
    }

    public function reverse(User $user, AccountingPayment $payment): bool
    {
        return $user->hasPermission('accounting.payments.approve_reversal')
            && $user->canAccessDataScopedRecord($payment)
            && ! $payment->bank_reconciled_at
            && in_array($payment->status, [
                AccountingPayment::STATUS_DRAFT,
                AccountingPayment::STATUS_POSTED,
                AccountingPayment::STATUS_RECONCILED,
            ], true);
    }
}
