<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrLeaveRequestPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.leave.view');
    }

    public function view(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $this->viewAny($user)
            && $this->canViewLeaveRequest($user, $leaveRequest);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.leave.manage');
    }

    public function update(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $this->create($user)
            && $this->view($user, $leaveRequest)
            && $leaveRequest->status === HrLeaveRequest::STATUS_DRAFT;
    }

    public function submit(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $this->create($user)
            && $this->view($user, $leaveRequest)
            && in_array($leaveRequest->status, [HrLeaveRequest::STATUS_DRAFT, HrLeaveRequest::STATUS_REJECTED], true);
    }

    public function cancel(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $this->view($user, $leaveRequest)
            && $leaveRequest->status !== HrLeaveRequest::STATUS_CANCELLED;
    }

    public function approve(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $user->hasPermission('hr.leave.approve')
            && $leaveRequest->status === HrLeaveRequest::STATUS_SUBMITTED
            && $this->canApproveLeaveRequest($user, $leaveRequest);
    }

    public function reject(User $user, HrLeaveRequest $leaveRequest): bool
    {
        return $this->approve($user, $leaveRequest);
    }
}
