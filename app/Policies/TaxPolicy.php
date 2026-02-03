<?php

namespace App\Policies;

use App\Core\MasterData\Models\Tax;
use App\Models\User;

class TaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.taxes.view');
    }

    public function view(User $user, Tax $tax): bool
    {
        return $user->hasPermission('core.taxes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.taxes.manage');
    }

    public function update(User $user, Tax $tax): bool
    {
        return $user->hasPermission('core.taxes.manage');
    }

    public function delete(User $user, Tax $tax): bool
    {
        return $user->hasPermission('core.taxes.manage');
    }
}
