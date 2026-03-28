<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrEmployeePolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.employees.view');
    }

    public function view(User $user, HrEmployee $employee): bool
    {
        return $this->viewAny($user)
            && $this->canViewEmployeeRecord($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.employees.manage');
    }

    public function update(User $user, HrEmployee $employee): bool
    {
        return $this->create($user)
            && $this->canManageEmployeeRecord($user, $employee);
    }

    public function delete(User $user, HrEmployee $employee): bool
    {
        return $this->update($user, $employee);
    }

    public function manageAccess(User $user, HrEmployee $employee): bool
    {
        return $this->update($user, $employee)
            && $user->hasPermission('hr.employee_access.manage');
    }

    public function viewPrivate(User $user, HrEmployee $employee): bool
    {
        return $this->view($user, $employee)
            && $this->canViewPrivateEmployeeRecord($user, $employee);
    }
}
