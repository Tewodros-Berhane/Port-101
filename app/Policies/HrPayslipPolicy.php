<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrPayslip;

class HrPayslipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.payroll.view');
    }

    public function view(User $user, HrPayslip $payslip): bool
    {
        if ((string) ($payslip->employee?->user_id ?? '') === (string) $user->id) {
            return true;
        }

        return $user->hasPermission('hr.payroll.manage')
            || $user->hasPermission('hr.payroll.approve')
            || $user->hasPermission('hr.payroll.post');
    }
}
