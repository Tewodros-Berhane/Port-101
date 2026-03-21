<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;

trait InteractsWithProjectAccess
{
    protected function canViewProjectRecord(User $user, Project $project): bool
    {
        return $user->canAccessDataScopedRecord($project)
            || $this->isProjectParticipant($user, $project);
    }

    protected function canManageProjectRecord(User $user, Project $project): bool
    {
        return $user->canAccessDataScopedRecord($project)
            || $this->isProjectManager($user, $project);
    }

    protected function canViewTaskRecord(User $user, ProjectTask $task): bool
    {
        return $user->canAccessDataScopedRecord($task)
            || $this->canViewProjectRecord($user, $task->project)
            || (string) $task->assigned_to === (string) $user->id;
    }

    protected function canManageTaskRecord(User $user, ProjectTask $task): bool
    {
        return $user->canAccessDataScopedRecord($task)
            || $this->canManageProjectRecord($user, $task->project)
            || (string) $task->assigned_to === (string) $user->id;
    }

    protected function canViewTimesheetRecord(
        User $user,
        ProjectTimesheet $timesheet
    ): bool {
        return $user->canAccessDataScopedRecord($timesheet)
            || $this->canViewProjectRecord($user, $timesheet->project)
            || (string) $timesheet->user_id === (string) $user->id;
    }

    protected function canManageOwnTimesheetRecord(
        User $user,
        ProjectTimesheet $timesheet
    ): bool {
        return $this->canViewTimesheetRecord($user, $timesheet)
            && (
                (string) $timesheet->user_id === (string) $user->id
                || (string) $timesheet->created_by === (string) $user->id
            );
    }

    protected function canManageTeamTimesheetRecord(
        User $user,
        ProjectTimesheet $timesheet
    ): bool {
        return $user->canAccessDataScopedRecord($timesheet)
            || $this->canManageProjectRecord($user, $timesheet->project);
    }

    protected function canViewBillableRecord(
        User $user,
        ProjectBillable $billable
    ): bool {
        return $user->canAccessDataScopedRecord($billable)
            || $this->canViewProjectRecord($user, $billable->project);
    }

    protected function canManageBillableRecord(
        User $user,
        ProjectBillable $billable
    ): bool {
        return $user->canAccessDataScopedRecord($billable)
            || $this->canManageProjectRecord($user, $billable->project);
    }

    private function isProjectParticipant(User $user, Project $project): bool
    {
        if ((string) $project->project_manager_id === (string) $user->id) {
            return true;
        }

        return ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function isProjectManager(User $user, Project $project): bool
    {
        if ((string) $project->project_manager_id === (string) $user->id) {
            return true;
        }

        return ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('project_role', ProjectMember::ROLE_MANAGER)
            ->exists();
    }
}
