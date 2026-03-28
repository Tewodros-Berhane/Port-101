<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrShift;

class HrShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.attendance.view');
    }

    public function view(User $user, HrShift $shift): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.attendance.manage');
    }

    public function update(User $user, HrShift $shift): bool
    {
        return $this->create($user);
    }
}
