<?php

namespace App\Policies;

use App\Core\MasterData\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.partners.view');
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->hasPermission('core.partners.view')
            && $user->canAccessDataScopedRecord($partner);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.partners.manage');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermission('core.partners.manage')
            && $user->canAccessDataScopedRecord($partner);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasPermission('core.partners.manage')
            && $user->canAccessDataScopedRecord($partner);
    }
}
