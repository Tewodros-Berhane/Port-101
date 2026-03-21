<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Sales\Models\SalesLead;

class SalesLeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sales.leads.view');
    }

    public function view(User $user, SalesLead $lead): bool
    {
        return $user->hasPermission('sales.leads.view')
            && $user->canAccessDataScopedRecord($lead);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales.leads.manage');
    }

    public function update(User $user, SalesLead $lead): bool
    {
        return $user->hasPermission('sales.leads.manage')
            && $user->canAccessDataScopedRecord($lead);
    }

    public function delete(User $user, SalesLead $lead): bool
    {
        return $user->hasPermission('sales.leads.manage')
            && $user->canAccessDataScopedRecord($lead);
    }
}
