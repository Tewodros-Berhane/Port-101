<?php

namespace App\Policies;

use App\Core\MasterData\Models\Uom;
use App\Models\User;

class UomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.uoms.view');
    }

    public function view(User $user, Uom $uom): bool
    {
        return $user->hasPermission('core.uoms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.uoms.manage');
    }

    public function update(User $user, Uom $uom): bool
    {
        return $user->hasPermission('core.uoms.manage');
    }

    public function delete(User $user, Uom $uom): bool
    {
        return $user->hasPermission('core.uoms.manage');
    }
}
