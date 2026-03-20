<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingBankReconciliationBatch;

class AccountingBankReconciliationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.bank_reconciliation.view');
    }

    public function view(User $user, AccountingBankReconciliationBatch $batch): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($batch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.bank_reconciliation.manage');
    }
}
