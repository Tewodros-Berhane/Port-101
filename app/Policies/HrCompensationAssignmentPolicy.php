<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrCompensationAssignment;

class HrCompensationAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage')
            || $user->hasPermission('hr.employees.private_view')
            || $user->hasPermission('hr.employees.private_manage');
    }

    public function view(User $user, HrCompensationAssignment $assignment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage');
    }

    public function update(User $user, HrCompensationAssignment $assignment): bool
    {
        return $this->create($user);
    }
}
