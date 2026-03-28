<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;

trait InteractsWithHrAccess
{
    protected function canViewEmployeeRecord(User $user, HrEmployee $employee): bool
    {
        return $user->canAccessDataScopedRecord($employee)
            || (string) $employee->user_id === (string) $user->id
            || (string) $employee->attendance_approver_user_id === (string) $user->id
            || (string) $employee->leave_approver_user_id === (string) $user->id
            || (string) $employee->reimbursement_approver_user_id === (string) $user->id
            || $this->isManagerOfEmployee($user, $employee);
    }

    protected function canManageEmployeeRecord(User $user, HrEmployee $employee): bool
    {
        return $user->canAccessDataScopedRecord($employee)
            || $this->isManagerOfEmployee($user, $employee);
    }

    protected function canViewPrivateEmployeeRecord(User $user, HrEmployee $employee): bool
    {
        return (string) $employee->user_id === (string) $user->id
            || $user->hasPermission('hr.employees.private_view')
            || $user->hasPermission('hr.employees.private_manage')
            || $this->isManagerOfEmployee($user, $employee);
    }

    protected function canManagePrivateEmployeeRecord(User $user, HrEmployee $employee): bool
    {
        return $user->hasPermission('hr.employees.private_manage')
            && $this->canManageEmployeeRecord($user, $employee);
    }

    private function isManagerOfEmployee(User $user, HrEmployee $employee): bool
    {
        if (! $employee->manager_employee_id) {
            return false;
        }

        return HrEmployee::query()
            ->whereKey($employee->manager_employee_id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
