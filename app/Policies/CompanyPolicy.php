<?php

namespace App\Policies;

use App\Core\Company\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.company.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('core.company.view', $company);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.company.manage');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('core.company.manage', $company);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('core.company.manage', $company);
    }
}
