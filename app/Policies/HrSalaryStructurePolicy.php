<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrSalaryStructure;

class HrSalaryStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.view');
    }

    public function view(User $user, HrSalaryStructure $structure): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage');
    }

    public function update(User $user, HrSalaryStructure $structure): bool
    {
        return $this->create($user);
    }
}
