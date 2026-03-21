<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Policies\Concerns\InteractsWithProjectAccess;

class ProjectTimesheetPolicy
{
    use InteractsWithProjectAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.timesheets.view');
    }

    public function view(User $user, ProjectTimesheet $timesheet): bool
    {
        return $this->viewAny($user)
            && $this->canViewTimesheetRecord($user, $timesheet);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.timesheets.manage_own')
            || $user->hasPermission('projects.timesheets.manage_team');
    }

    public function update(User $user, ProjectTimesheet $timesheet): bool
    {
        if (! in_array($timesheet->approval_status, [
            ProjectTimesheet::APPROVAL_STATUS_DRAFT,
            ProjectTimesheet::APPROVAL_STATUS_REJECTED,
        ], true)) {
            return false;
        }

        return ($user->hasPermission('projects.timesheets.manage_team')
                && $this->canManageTeamTimesheetRecord($user, $timesheet))
            || ($user->hasPermission('projects.timesheets.manage_own')
                && $this->canManageOwnTimesheetRecord($user, $timesheet));
    }

    public function delete(User $user, ProjectTimesheet $timesheet): bool
    {
        return $this->update($user, $timesheet);
    }

    public function submit(User $user, ProjectTimesheet $timesheet): bool
    {
        return $this->update($user, $timesheet);
    }

    public function approve(User $user, ProjectTimesheet $timesheet): bool
    {
        return $user->hasPermission('projects.timesheets.approve')
            && $this->canManageTeamTimesheetRecord($user, $timesheet)
            && $timesheet->approval_status === ProjectTimesheet::APPROVAL_STATUS_SUBMITTED;
    }

    public function reject(User $user, ProjectTimesheet $timesheet): bool
    {
        return $this->approve($user, $timesheet);
    }
}
