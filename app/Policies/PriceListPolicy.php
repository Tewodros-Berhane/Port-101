<?php

namespace App\Policies;

use App\Core\MasterData\Models\PriceList;
use App\Models\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.price_lists.view');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('core.price_lists.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.price_lists.manage');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('core.price_lists.manage');
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('core.price_lists.manage');
    }
}
