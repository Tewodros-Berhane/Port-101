<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrAttendanceRecordPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.attendance.view');
    }

    public function view(User $user, HrAttendanceRecord $attendanceRecord): bool
    {
        return $this->viewAny($user)
            && $this->canViewAttendanceRecord($user, $attendanceRecord);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.attendance.manage');
    }
}
