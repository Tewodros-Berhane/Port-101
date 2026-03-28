<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrLeaveType;

class HrLeaveTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.leave.view');
    }

    public function view(User $user, HrLeaveType $leaveType): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.leave.manage');
    }

    public function update(User $user, HrLeaveType $leaveType): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, HrLeaveType $leaveType): bool
    {
        return $this->create($user);
    }
}
