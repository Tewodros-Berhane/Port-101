<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrPayrollRun;

class HrPayrollRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage')
            || $user->hasPermission('hr.payroll.approve')
            || $user->hasPermission('hr.payroll.post');
    }

    public function view(User $user, HrPayrollRun $run): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.payroll.manage');
    }

    public function prepare(User $user, HrPayrollRun $run): bool
    {
        return $user->hasPermission('hr.payroll.manage');
    }

    public function approve(User $user, HrPayrollRun $run): bool
    {
        return $user->hasPermission('hr.payroll.approve');
    }

    public function reject(User $user, HrPayrollRun $run): bool
    {
        return $user->hasPermission('hr.payroll.approve');
    }

    public function post(User $user, HrPayrollRun $run): bool
    {
        return $user->hasPermission('hr.payroll.post');
    }
}
