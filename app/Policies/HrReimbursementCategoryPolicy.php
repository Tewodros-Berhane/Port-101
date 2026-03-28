<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrReimbursementCategory;

class HrReimbursementCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.reimbursements.view');
    }

    public function view(User $user, HrReimbursementCategory $category): bool
    {
        return $this->viewAny($user)
            && $user->canAccessDataScopedRecord($category);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.reimbursements.manage');
    }

    public function update(User $user, HrReimbursementCategory $category): bool
    {
        return $this->create($user)
            && $user->canAccessDataScopedRecord($category);
    }
}
