<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrPayrollPeriod;

class HrPayrollPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage')
            || $user->hasPermission('hr.payroll.approve')
            || $user->hasPermission('hr.payroll.post');
    }

    public function view(User $user, HrPayrollPeriod $period): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage');
    }

    public function update(User $user, HrPayrollPeriod $period): bool
    {
        return $this->create($user);
    }
}
