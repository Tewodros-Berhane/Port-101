<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\AccountingManualJournal;

class AccountingManualJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.manual_journals.view');
    }

    public function view(User $user, AccountingManualJournal $manualJournal): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($manualJournal);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.manual_journals.manage');
    }

    public function update(User $user, AccountingManualJournal $manualJournal): bool
    {
        return $user->hasPermission('accounting.manual_journals.manage')
            && $user->canAccessDataScopedRecord($manualJournal)
            && $manualJournal->status === AccountingManualJournal::STATUS_DRAFT;
    }

    public function delete(User $user, AccountingManualJournal $manualJournal): bool
    {
        return $this->update($user, $manualJournal);
    }

    public function post(User $user, AccountingManualJournal $manualJournal): bool
    {
        return $user->hasPermission('accounting.manual_journals.post')
            && $user->canAccessDataScopedRecord($manualJournal)
            && $manualJournal->status === AccountingManualJournal::STATUS_DRAFT
            && in_array($manualJournal->approval_status, [
                AccountingManualJournal::APPROVAL_STATUS_NOT_REQUIRED,
                AccountingManualJournal::APPROVAL_STATUS_APPROVED,
            ], true);
    }

    public function reverse(User $user, AccountingManualJournal $manualJournal): bool
    {
        return $user->hasPermission('accounting.manual_journals.post')
            && $user->canAccessDataScopedRecord($manualJournal)
            && $manualJournal->status === AccountingManualJournal::STATUS_POSTED;
    }
}
