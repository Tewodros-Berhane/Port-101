<?php

namespace App\Policies;

use App\Modules\Sales\Models\SalesQuote;
use App\Models\User;

class SalesQuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.quotes.view');
    }

    public function view(User $user, SalesQuote $quote): bool
    {
        return $user->hasPermission('sales.quotes.view')
            && $user->canAccessDataScopedRecord($quote);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.quotes.manage');
    }

    public function update(User $user, SalesQuote $quote): bool
    {
        return $user->hasPermission('sales.quotes.manage')
            && $user->canAccessDataScopedRecord($quote)
            && $quote->status !== SalesQuote::STATUS_CONFIRMED;
    }

    public function delete(User $user, SalesQuote $quote): bool
    {
        return $user->hasPermission('sales.quotes.manage')
            && $user->canAccessDataScopedRecord($quote)
            && $quote->status !== SalesQuote::STATUS_CONFIRMED;
    }

    public function approve(User $user, SalesQuote $quote): bool
    {
        return $user->hasPermission('sales.quotes.approve')
            && $user->canAccessDataScopedRecord($quote)
            && $quote->status !== SalesQuote::STATUS_CONFIRMED;
    }

    public function confirm(User $user, SalesQuote $quote): bool
    {
        return $user->hasPermission('sales.quotes.manage')
            && $user->canAccessDataScopedRecord($quote);
    }
}


