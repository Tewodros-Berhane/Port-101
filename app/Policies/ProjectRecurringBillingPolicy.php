<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectRecurringBillingPolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.recurring_billing.view');
    }

    public function view(User $user, ProjectRecurringBilling $recurringBilling): bool
    {
        return $this->viewAny($user)
            && $this->canViewProjectRecord($user, $recurringBilling->project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.recurring_billing.manage');
    }

    public function update(User $user, ProjectRecurringBilling $recurringBilling): bool
    {
        return $user->hasPermission('projects.recurring_billing.manage')
            && $this->canManageProjectRecord($user, $recurringBilling->project);
    }

    public function run(User $user, ProjectRecurringBilling $recurringBilling): bool
    {
        return $user->hasPermission('projects.recurring_billing.run')
            && $this->canManageProjectRecord($user, $recurringBilling->project);
    }
}
