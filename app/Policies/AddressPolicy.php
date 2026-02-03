<?php

namespace App\Policies;

use App\Core\MasterData\Models\Address;
use App\Models\User;

class AddressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.addresses.view');
    }

    public function view(User $user, Address $address): bool
    {
        return $user->hasPermission('core.addresses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.addresses.manage');
    }

    public function update(User $user, Address $address): bool
    {
        return $user->hasPermission('core.addresses.manage');
    }

    public function delete(User $user, Address $address): bool
    {
        return $user->hasPermission('core.addresses.manage');
    }
}
