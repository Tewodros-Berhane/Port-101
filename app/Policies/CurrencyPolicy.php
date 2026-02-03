<?php

namespace App\Policies;

use App\Core\MasterData\Models\Currency;
use App\Models\User;

class CurrencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.currencies.view');
    }

    public function view(User $user, Currency $currency): bool
    {
        return $user->hasPermission('core.currencies.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.currencies.manage');
    }

    public function update(User $user, Currency $currency): bool
    {
        return $user->hasPermission('core.currencies.manage');
    }

    public function delete(User $user, Currency $currency): bool
    {
        return $user->hasPermission('core.currencies.manage');
    }
}
