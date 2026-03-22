<?php

namespace App\Http\Controllers\Projects;

use App\Core\Company\Models\CompanyUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectTimesheetRejectRequest;
use App\Http\Requests\Projects\ProjectTimesheetStoreRequest;
use App\Http\Requests\Projects\ProjectTimesheetUpdateRequest;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Projects\ProjectTimesheetWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTimesheetsController extends Controller
{
    public function create(Project $project, Request $request): Response
    {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectTimesheet::class);

        $canManageTeamTimesheets = $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false;
        $taskOptions = $this->taskOptions($project);
        $defaultTaskId = $taskOptions[0]['id'] ?? '';

        return Inertia::render('projects/timesheets/create', [
            'project' => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
            ],
            'timesheet' => [
                'user_id' => $canManageTeamTimesheets ? '' : (string) $request->user()?->id,
                'task_id' => $defaultTaskId,
                'work_date' => now()->toDateString(),
                'description' => '',
                'hours' => '1.00',
                'is_billable' => true,
                'cost_rate' => '',
                'bill_rate' => '',
            ],
            'taskOptions' => $taskOptions,
            'userOptions' => $this->userOptions($request),
            'abilities' => [
                'can_manage_team_timesheets' => $canManageTeamTimesheets,
            ],
        ]);
    }

    public function store(
        ProjectTimesheetStoreRequest $request,
        Project $project,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectTimesheet::class);

        $timesheet = $workflowService->createDraft(
            project: $project,
            attributes: $request->validated(),
            canManageTeamTimesheets: $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.timesheets.edit', $timesheet)
            ->with('success', 'Timesheet created.');
    }

    public function edit(ProjectTimesheet $timesheet, Request $request): Response
    {
        $this->authorize('view', $timesheet);

        $timesheet->load([
            'project:id,project_code,name',
            'task:id,project_id,task_number,title',
            'user:id,name,email',
            'approvedBy:id,name',
        ]);

        $canManageTeamTimesheets = $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false;

        return Inertia::render('projects/timesheets/edit', [
            'project' => [
                'id' => $timesheet->project->id,
                'project_code' => $timesheet->project->project_code,
                'name' => $timesheet->project->name,
            ],
            'timesheet' => [
                'id' => $timesheet->id,
                'user_id' => $timesheet->user_id,
                'user_name' => $timesheet->user?->name,
                'task_id' => $timesheet->task_id,
                'task_number' => $timesheet->task?->task_number,
                'task_title' => $timesheet->task?->title,
                'work_date' => $timesheet->work_date?->toDateString(),
                'description' => $timesheet->description,
                'hours' => (string) $timesheet->hours,
                'is_billable' => (bool) $timesheet->is_billable,
                'cost_rate' => (string) $timesheet->cost_rate,
                'bill_rate' => (string) $timesheet->bill_rate,
                'cost_amount' => (float) $timesheet->cost_amount,
                'billable_amount' => (float) $timesheet->billable_amount,
                'approval_status' => $timesheet->approval_status,
                'approved_by_name' => $timesheet->approvedBy?->name,
                'approved_at' => $timesheet->approved_at?->toIso8601String(),
                'rejection_reason' => $timesheet->rejection_reason,
                'invoice_status' => $timesheet->invoice_status,
                'updated_at' => $timesheet->updated_at?->toIso8601String(),
            ],
            'taskOptions' => $this->taskOptions($timesheet->project),
            'userOptions' => $this->userOptions($request),
            'abilities' => [
                'can_manage_team_timesheets' => $canManageTeamTimesheets,
                'can_edit_timesheet' => $request->user()?->can('update', $timesheet) ?? false,
                'can_submit_timesheet' => $request->user()?->can('submit', $timesheet) ?? false,
                'can_approve_timesheet' => $request->user()?->can('approve', $timesheet) ?? false,
                'can_reject_timesheet' => $request->user()?->can('reject', $timesheet) ?? false,
                'can_delete_timesheet' => $request->user()?->can('delete', $timesheet) ?? false,
            ],
        ]);
    }

    public function update(
        ProjectTimesheetUpdateRequest $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('update', $timesheet);

        $timesheet = $workflowService->updateDraft(
            timesheet: $timesheet,
            attributes: $request->validated(),
            canManageTeamTimesheets: $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false,
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.timesheets.edit', $timesheet)
            ->with('success', 'Timesheet updated.');
    }

    public function submit(
        Request $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('submit', $timesheet);

        $workflowService->submit($timesheet, $request->user()?->id);

        return redirect()
            ->route('company.projects.timesheets.edit', $timesheet)
            ->with('success', 'Timesheet submitted for approval.');
    }

    public function approve(
        Request $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('approve', $timesheet);

        $workflowService->approve($timesheet, $request->user()?->id);

        return redirect()
            ->route('company.projects.timesheets.edit', $timesheet)
            ->with('success', 'Timesheet approved.');
    }

    public function reject(
        ProjectTimesheetRejectRequest $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('reject', $timesheet);

        $workflowService->reject(
            timesheet: $timesheet,
            reason: $request->validated('reason'),
            actorId: $request->user()?->id,
        );

        return redirect()
            ->route('company.projects.timesheets.edit', $timesheet)
            ->with('success', 'Timesheet rejected.');
    }

    public function destroy(
        ProjectTimesheet $timesheet,
        Request $request,
        ProjectTimesheetWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('delete', $timesheet);

        $project = $timesheet->project;

        $workflowService->delete($timesheet);

        return redirect()
            ->route('company.projects.show', $project)
            ->with('success', 'Timesheet deleted.');
    }

    /**
     * @return array<int, array{id: string, task_number: string, title: string, assignee_name: string|null}>
     */
    private function taskOptions(Project $project): array
    {
        return ProjectTask::query()
            ->with('assignee:id,name')
            ->where('project_id', $project->id)
            ->orderBy('task_number')
            ->get(['id', 'task_number', 'title', 'assigned_to'])
            ->map(fn (ProjectTask $task) => [
                'id' => $task->id,
                'task_number' => $task->task_number,
                'title' => $task->title,
                'assignee_name' => $task->assignee?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, email: string, role_name: string|null}>
     */
    private function userOptions(Request $request): array
    {
        $companyId = (string) $request->user()?->current_company_id;

        if ($companyId === '') {
            return [];
        }

        return CompanyUser::query()
            ->with(['user:id,name,email', 'role:id,name'])
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (CompanyUser $membership) => $membership->user !== null)
            ->sortBy(fn (CompanyUser $membership) => strtolower((string) $membership->user?->name))
            ->values()
            ->map(fn (CompanyUser $membership) => [
                'id' => (string) $membership->user_id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
                'role_name' => $membership->is_owner
                    ? 'Owner'
                    : $membership->role?->name,
            ])
            ->all();
    }
}
