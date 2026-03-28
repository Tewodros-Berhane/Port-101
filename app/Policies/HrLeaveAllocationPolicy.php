<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrLeaveAllocationPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.leave.view');
    }

    public function view(User $user, HrLeaveAllocation $allocation): bool
    {
        return $this->viewAny($user)
            && $this->canViewLeaveAllocation($user, $allocation);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.leave.manage');
    }

    public function update(User $user, HrLeaveAllocation $allocation): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, HrLeaveAllocation $allocation): bool
    {
        return $this->create($user);
    }
}
