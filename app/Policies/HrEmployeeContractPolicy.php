<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrEmployeeContractPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.employees.view');
    }

    public function view(User $user, HrEmployeeContract $contract): bool
    {
        return $this->viewAny($user)
            && $this->canViewEmployeeRecord($user, $contract->employee)
            && $this->canViewPrivateEmployeeRecord($user, $contract->employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.employees.private_manage');
    }

    public function update(User $user, HrEmployeeContract $contract): bool
    {
        return $this->create($user)
            && $this->canManagePrivateEmployeeRecord($user, $contract->employee);
    }

    public function delete(User $user, HrEmployeeContract $contract): bool
    {
        return $this->update($user, $contract);
    }
}
