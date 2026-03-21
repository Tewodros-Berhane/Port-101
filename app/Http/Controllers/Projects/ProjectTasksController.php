<?php

namespace App\Http\Controllers\Projects;

use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Partner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectTaskStoreRequest;
use App\Http\Requests\Projects\ProjectTaskUpdateRequest;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\ProjectWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectTasksController extends Controller
{
    public function create(
        Project $project,
        Request $request,
        ProjectWorkspaceService $workspaceService
    ): Response {
        $this->authorize('view', $project);
        abort_unless($request->user()?->can('update', $project), 403);

        $stages = $this->stageOptions($project, $request, $workspaceService);

        return Inertia::render('projects/tasks/create', [
            'project' => [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'customer_id' => $project->customer_id,
            ],
            'task' => [
                'task_number' => '',
                'title' => '',
                'description' => '',
                'stage_id' => $stages[0]['id'] ?? '',
                'parent_task_id' => '',
                'customer_id' => $project->customer_id ?? '',
                'status' => ProjectTask::STATUS_TODO,
                'priority' => ProjectTask::PRIORITY_MEDIUM,
                'assigned_to' => '',
                'start_date' => now()->toDateString(),
                'due_date' => '',
                'estimated_hours' => '',
                'is_billable' => true,
                'billing_status' => ProjectTask::BILLING_STATUS_NOT_READY,
            ],
            'stages' => $stages,
            'assignees' => $this->assigneeOptions($request),
            'customers' => $this->customerOptions(),
            'parentTasks' => $this->parentTaskOptions($project),
            'statuses' => ProjectTask::STATUSES,
            'priorities' => ProjectTask::PRIORITIES,
            'billingStatuses' => ProjectTask::BILLING_STATUSES,
        ]);
    }

    public function store(
        ProjectTaskStoreRequest $request,
        Project $project,
        ProjectWorkspaceService $workspaceService
    ): RedirectResponse {
        $this->authorize('view', $project);
        abort_unless($request->user()?->can('update', $project), 403);

        $stages = $this->stageOptions($project, $request, $workspaceService);
        $validated = $request->validated();

        $task = ProjectTask::create([
            ...$validated,
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'stage_id' => $validated['stage_id'] ?? ($stages[0]['id'] ?? null),
            'customer_id' => $validated['customer_id'] ?? $project->customer_id,
            'completed_at' => ($validated['status'] ?? null) === ProjectTask::STATUS_DONE
                ? now()
                : null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $workspaceService->syncTaskAssigneeMember(
            project: $project,
            assigneeId: $task->assigned_to ? (string) $task->assigned_to : null,
            actorId: $request->user()?->id,
        );
        $workspaceService->refreshProjectRollup($project);

        return redirect()
            ->route('company.projects.tasks.edit', $task)
            ->with('success', 'Task created.');
    }

    public function edit(
        ProjectTask $task,
        Request $request,
        ProjectWorkspaceService $workspaceService
    ): Response {
        $this->authorize('update', $task);

        $task->load(['project:id,company_id,name,project_code,customer_id']);

        return Inertia::render('projects/tasks/edit', [
            'project' => [
                'id' => $task->project->id,
                'project_code' => $task->project->project_code,
                'name' => $task->project->name,
                'customer_id' => $task->project->customer_id,
            ],
            'task' => [
                'id' => $task->id,
                'task_number' => $task->task_number,
                'title' => $task->title,
                'description' => $task->description,
                'stage_id' => $task->stage_id,
                'parent_task_id' => $task->parent_task_id,
                'customer_id' => $task->customer_id,
                'status' => $task->status,
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
                'start_date' => $task->start_date?->toDateString(),
                'due_date' => $task->due_date?->toDateString(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'estimated_hours' => $task->estimated_hours !== null
                    ? (string) $task->estimated_hours
                    : '',
                'actual_hours' => (float) $task->actual_hours,
                'is_billable' => (bool) $task->is_billable,
                'billing_status' => $task->billing_status,
            ],
            'stages' => $this->stageOptions(
                $task->project,
                $request,
                $workspaceService,
            ),
            'assignees' => $this->assigneeOptions($request),
            'customers' => $this->customerOptions(),
            'parentTasks' => $this->parentTaskOptions($task->project, $task),
            'statuses' => ProjectTask::STATUSES,
            'priorities' => ProjectTask::PRIORITIES,
            'billingStatuses' => ProjectTask::BILLING_STATUSES,
            'abilities' => [
                'can_assign_task' => $request->user()?->can('assign', $task) ?? false,
            ],
        ]);
    }

    public function update(
        ProjectTaskUpdateRequest $request,
        ProjectTask $task,
        ProjectWorkspaceService $workspaceService
    ): RedirectResponse {
        $this->authorize('update', $task);

        $validated = $request->validated();
        $assignedToChanged = (string) ($validated['assigned_to'] ?? '')
            !== (string) ($task->assigned_to ?? '');

        if ($assignedToChanged && ! $request->user()?->can('assign', $task)) {
            abort(403);
        }

        $task->update([
            ...$validated,
            'completed_at' => ($validated['status'] ?? null) === ProjectTask::STATUS_DONE
                ? ($task->completed_at ?? now())
                : null,
            'updated_by' => $request->user()?->id,
        ]);

        $workspaceService->syncTaskAssigneeMember(
            project: $task->project,
            assigneeId: $task->assigned_to ? (string) $task->assigned_to : null,
            actorId: $request->user()?->id,
        );
        $workspaceService->refreshProjectRollup($task->project);

        return redirect()
            ->route('company.projects.tasks.edit', $task)
            ->with('success', 'Task updated.');
    }

    public function destroy(
        ProjectTask $task,
        ProjectWorkspaceService $workspaceService
    ): RedirectResponse {
        $this->authorize('delete', $task);

        $project = $task->project;
        $task->delete();
        $workspaceService->refreshProjectRollup($project);

        return redirect()
            ->route('company.projects.show', $project)
            ->with('success', 'Task removed.');
    }

    /**
     * @return array<int, array{id: string, name: string, color: string|null, is_closed_stage: bool}>
     */
    private function stageOptions(
        Project $project,
        Request $request,
        ProjectWorkspaceService $workspaceService
    ): array {
        return $workspaceService->ensureDefaultStages(
            companyId: (string) $project->company_id,
            actorId: $request->user()?->id,
        )
            ->map(fn (ProjectStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'is_closed_stage' => $stage->is_closed_stage,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, email: string, role_name: string|null}>
     */
    private function assigneeOptions(Request $request): array
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

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function customerOptions(): array
    {
        return Partner::query()
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, task_number: string, title: string}>
     */
    private function parentTaskOptions(
        Project $project,
        ?ProjectTask $exceptTask = null
    ): array {
        return ProjectTask::query()
            ->where('project_id', $project->id)
            ->when(
                $exceptTask,
                fn ($builder) => $builder->where('id', '!=', $exceptTask?->id),
            )
            ->orderBy('task_number')
            ->get(['id', 'task_number', 'title'])
            ->map(fn (ProjectTask $task) => [
                'id' => $task->id,
                'task_number' => $task->task_number,
                'title' => $task->title,
            ])
            ->values()
            ->all();
    }
}
