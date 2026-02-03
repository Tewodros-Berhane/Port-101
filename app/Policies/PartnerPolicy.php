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
        return $user->hasPermission('core.partners.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.partners.manage');
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->hasPermission('core.partners.manage');
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->hasPermission('core.partners.manage');
    }
}
