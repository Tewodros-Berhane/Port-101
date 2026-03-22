<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectBillable;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectBillablePolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.billables.view');
    }

    public function view(User $user, ProjectBillable $billable): bool
    {
        return $this->viewAny($user)
            && $this->canViewBillableRecord($user, $billable);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.billables.manage');
    }

    public function update(User $user, ProjectBillable $billable): bool
    {
        return $user->hasPermission('projects.billables.manage')
            && $this->canManageBillableRecord($user, $billable);
    }

    public function delete(User $user, ProjectBillable $billable): bool
    {
        return $this->update($user, $billable);
    }

    public function approve(User $user, ProjectBillable $billable): bool
    {
        return $user->hasPermission('projects.billables.approve')
            && $this->canManageBillableRecord($user, $billable);
    }

    public function reject(User $user, ProjectBillable $billable): bool
    {
        return $this->approve($user, $billable);
    }

    public function cancel(User $user, ProjectBillable $billable): bool
    {
        return $this->update($user, $billable);
    }

    public function createInvoice(User $user, ProjectBillable $billable): bool
    {
        return $user->hasPermission('projects.invoices.create')
            && $this->canViewBillableRecord($user, $billable);
    }
}
