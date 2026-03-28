<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrReimbursementClaimLine;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrReimbursementClaimPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.reimbursements.view');
    }

    public function view(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->viewAny($user)
            && $this->canViewReimbursementClaim($user, $claim);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.reimbursements.manage');
    }

    public function update(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->create($user)
            && $this->view($user, $claim)
            && in_array($claim->status, [
                HrReimbursementClaim::STATUS_DRAFT,
                HrReimbursementClaim::STATUS_REJECTED,
            ], true);
    }

    public function submit(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->update($user, $claim);
    }

    public function approve(User $user, HrReimbursementClaim $claim): bool
    {
        return $user->hasPermission('hr.reimbursements.approve')
            && in_array($claim->status, [
                HrReimbursementClaim::STATUS_SUBMITTED,
                HrReimbursementClaim::STATUS_MANAGER_APPROVED,
            ], true)
            && $this->canApproveReimbursementClaim($user, $claim);
    }

    public function reject(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->approve($user, $claim);
    }

    public function postToAccounting(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->view($user, $claim)
            && $user->hasPermission('hr.reimbursements.approve')
            && $user->hasPermission('accounting.invoices.manage')
            && $user->hasPermission('accounting.invoices.post')
            && in_array($claim->status, [
                HrReimbursementClaim::STATUS_FINANCE_APPROVED,
                HrReimbursementClaim::STATUS_POSTED,
            ], true);
    }

    public function recordPayment(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->view($user, $claim)
            && $user->hasPermission('hr.reimbursements.approve')
            && $user->hasPermission('accounting.payments.manage')
            && in_array($claim->status, [
                HrReimbursementClaim::STATUS_POSTED,
                HrReimbursementClaim::STATUS_PAID,
            ], true);
    }

    public function uploadReceipt(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->update($user, $claim);
    }

    public function removeReceipt(User $user, HrReimbursementClaim $claim): bool
    {
        return $this->uploadReceipt($user, $claim);
    }

    public function manageLine(User $user, HrReimbursementClaimLine $line): bool
    {
        $claim = $line->claim;

        return $claim instanceof HrReimbursementClaim
            && $this->uploadReceipt($user, $claim);
    }
}
