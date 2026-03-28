<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrShiftAssignment;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrShiftAssignmentPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.attendance.view');
    }

    public function view(User $user, HrShiftAssignment $assignment): bool
    {
        $employee = $assignment->employee;

        return $this->viewAny($user)
            && $employee instanceof HrEmployee
            && $this->canViewEmployeeRecord($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.attendance.manage');
    }

    public function update(User $user, HrShiftAssignment $assignment): bool
    {
        $employee = $assignment->employee;

        return $this->create($user)
            && $employee instanceof HrEmployee
            && $this->canManageEmployeeRecord($user, $employee);
    }
}
