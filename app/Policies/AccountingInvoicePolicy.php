<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingInvoice;

class AccountingInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.invoices.view');
    }

    public function view(User $user, AccountingInvoice $invoice): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($invoice);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.invoices.manage');
    }

    public function update(User $user, AccountingInvoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoices.manage')
            && $user->canAccessDataScopedRecord($invoice)
            && $invoice->status === AccountingInvoice::STATUS_DRAFT;
    }

    public function delete(User $user, AccountingInvoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function post(User $user, AccountingInvoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoices.post')
            && $user->canAccessDataScopedRecord($invoice)
            && $invoice->status === AccountingInvoice::STATUS_DRAFT;
    }

    public function cancel(User $user, AccountingInvoice $invoice): bool
    {
        return $user->hasPermission('accounting.invoices.post')
            && $user->canAccessDataScopedRecord($invoice)
            && in_array($invoice->status, [
                AccountingInvoice::STATUS_DRAFT,
                AccountingInvoice::STATUS_POSTED,
            ], true);
    }
}
