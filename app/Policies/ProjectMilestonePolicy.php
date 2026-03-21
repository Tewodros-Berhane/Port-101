<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectMilestonePolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.milestones.view');
    }

    public function view(User $user, ProjectMilestone $milestone): bool
    {
        return $this->viewAny($user)
            && $this->canViewProjectRecord($user, $milestone->project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.milestones.manage');
    }

    public function update(User $user, ProjectMilestone $milestone): bool
    {
        return $user->hasPermission('projects.milestones.manage')
            && $this->canManageProjectRecord($user, $milestone->project);
    }

    public function delete(User $user, ProjectMilestone $milestone): bool
    {
        return $this->update($user, $milestone);
    }
}
