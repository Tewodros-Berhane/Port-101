<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('approvals.requests.view')
            || $user->hasPermission('approvals.requests.manage');
    }

    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return (
            $user->hasPermission('approvals.requests.view')
            || $user->hasPermission('approvals.requests.manage')
        )
            && $user->canAccessDataScopedRecord($approvalRequest);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('approvals.requests.manage');
    }

    public function update(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->hasPermission('approvals.requests.manage')
            && $user->canAccessDataScopedRecord($approvalRequest);
    }

    public function approve(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->hasPermission('approvals.requests.manage')
            && $user->canAccessDataScopedRecord($approvalRequest);
    }

    public function reject(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->hasPermission('approvals.requests.manage')
            && $user->canAccessDataScopedRecord($approvalRequest);
    }
}
