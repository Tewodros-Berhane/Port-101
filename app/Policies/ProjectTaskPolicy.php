<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectTask;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectTaskPolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.tasks.view');
    }

    public function view(User $user, ProjectTask $task): bool
    {
        return $this->viewAny($user)
            && $this->canViewTaskRecord($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.tasks.manage');
    }

    public function update(User $user, ProjectTask $task): bool
    {
        return $user->hasPermission('projects.tasks.manage')
            && $this->canManageTaskRecord($user, $task);
    }

    public function delete(User $user, ProjectTask $task): bool
    {
        return $this->update($user, $task);
    }

    public function assign(User $user, ProjectTask $task): bool
    {
        return $user->hasPermission('projects.tasks.assign')
            && $this->canManageProjectRecord($user, $task->project);
    }
}
