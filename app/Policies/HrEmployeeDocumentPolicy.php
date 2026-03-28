<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployeeDocument;
use App\Policies\Concerns\InteractsWithHrAccess;

class HrEmployeeDocumentPolicy
{
    use InteractsWithHrAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('hr.employees.view');
    }

    public function view(User $user, HrEmployeeDocument $document): bool
    {
        if (! $this->viewAny($user) || ! $this->canViewEmployeeRecord($user, $document->employee)) {
            return false;
        }

        if (! $document->is_private) {
            return true;
        }

        return $this->canViewPrivateEmployeeRecord($user, $document->employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('hr.employees.private_manage');
    }

    public function update(User $user, HrEmployeeDocument $document): bool
    {
        return $this->create($user)
            && $this->canManagePrivateEmployeeRecord($user, $document->employee);
    }

    public function delete(User $user, HrEmployeeDocument $document): bool
    {
        return $this->update($user, $document);
    }
}
