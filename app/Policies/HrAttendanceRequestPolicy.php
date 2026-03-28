<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrAttendanceRequestPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.attendance.view');
    }

    public function view(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $this->viewAny($user)
            && $this->canViewAttendanceRequest($user, $attendanceRequest);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.attendance.manage');
    }

    public function update(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $this->create($user)
            && $this->view($user, $attendanceRequest)
            && $attendanceRequest->status === HrAttendanceRequest::STATUS_DRAFT;
    }

    public function submit(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $this->create($user)
            && $this->view($user, $attendanceRequest)
            && in_array($attendanceRequest->status, [HrAttendanceRequest::STATUS_DRAFT, HrAttendanceRequest::STATUS_REJECTED], true);
    }

    public function cancel(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $this->view($user, $attendanceRequest)
            && in_array($attendanceRequest->status, [
                HrAttendanceRequest::STATUS_DRAFT,
                HrAttendanceRequest::STATUS_SUBMITTED,
                HrAttendanceRequest::STATUS_REJECTED,
            ], true);
    }

    public function approve(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $user->hasPermission('hr.attendance.approve')
            && $attendanceRequest->status === HrAttendanceRequest::STATUS_SUBMITTED
            && $this->canApproveAttendanceRequest($user, $attendanceRequest);
    }

    public function reject(User $user, HrAttendanceRequest $attendanceRequest): bool
    {
        return $this->approve($user, $attendanceRequest);
    }
}
