<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectPolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user)
            && $this->canViewProjectRecord($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.projects.manage')
            && $this->canManageProjectRecord($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
