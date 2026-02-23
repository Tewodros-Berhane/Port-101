<?php

namespace App\Policies;

use App\Core\MasterData\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('core.products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasPermission('core.products.view')
            && $user->canAccessDataScopedRecord($product);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('core.products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermission('core.products.manage')
            && $user->canAccessDataScopedRecord($product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('core.products.manage')
            && $user->canAccessDataScopedRecord($product);
    }
}
