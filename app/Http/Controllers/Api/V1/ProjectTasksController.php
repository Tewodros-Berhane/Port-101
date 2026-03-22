<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Projects\ProjectTaskStoreRequest;
use App\Http\Requests\Projects\ProjectTaskUpdateRequest;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\ProjectWorkspaceService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTasksController extends ApiController
{
    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', ProjectTask::class);

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $assignedTo = trim((string) $request->input('assigned_to', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['task_number', 'title', 'status', 'due_date', 'updated_at'],
            defaultSort: 'task_number',
            defaultDirection: 'asc',
        );

        $tasks = ProjectTask::query()
            ->with(['stage:id,name', 'assignee:id,name'])
            ->where('project_id', $project->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('task_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($assignedTo !== '', fn ($query) => $query->where('assigned_to', $assignedTo))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $tasks,
            data: collect($tasks->items())
                ->map(fn (ProjectTask $task) => $this->mapTask($task, $request->user()))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'assigned_to' => $assignedTo,
            ],
        );
    }

    public function store(
        ProjectTaskStoreRequest $request,
        Project $project,
        ProjectWorkspaceService $workspaceService
    ): JsonResponse {
        $this->authorize('view', $project);
        abort_unless($request->user()?->can('update', $project), 403);

        $validated = $request->validated();
        $stages = $workspaceService->ensureDefaultStages(
            companyId: (string) $project->company_id,
            actorId: $request->user()?->id,
        );

        $task = ProjectTask::create([
            ...$validated,
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'stage_id' => $validated['stage_id'] ?? ($stages->first()?->id ?? null),
            'customer_id' => $validated['customer_id'] ?? $project->customer_id,
            'completed_at' => ($validated['status'] ?? null) === ProjectTask::STATUS_DONE ? now() : null,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $workspaceService->syncTaskAssigneeMember(
            project: $project,
            assigneeId: $task->assigned_to ? (string) $task->assigned_to : null,
            actorId: $request->user()?->id,
        );
        $workspaceService->refreshProjectRollup($project);

        return $this->respond(
            $this->mapTask(
                $task->fresh(['stage:id,name', 'assignee:id,name', 'project:id,project_code,name']),
                $request->user(),
            ),
            201,
        );
    }

    public function show(ProjectTask $task, Request $request): JsonResponse
    {
        $this->authorize('view', $task);

        return $this->respond(
            $this->mapTask(
                $task->load(['stage:id,name', 'assignee:id,name', 'project:id,project_code,name']),
                $request->user(),
            ),
        );
    }

    public function update(
        ProjectTaskUpdateRequest $request,
        ProjectTask $task,
        ProjectWorkspaceService $workspaceService
    ): JsonResponse {
        $this->authorize('update', $task);

        $validated = $request->validated();
        $assignedToChanged = (string) ($validated['assigned_to'] ?? '') !== (string) ($task->assigned_to ?? '');

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

        return $this->respond(
            $this->mapTask(
                $task->fresh(['stage:id,name', 'assignee:id,name', 'project:id,project_code,name']),
                $request->user(),
            ),
        );
    }

    public function destroy(
        ProjectTask $task,
        ProjectWorkspaceService $workspaceService
    ): JsonResponse {
        $this->authorize('delete', $task);

        $project = $task->project;
        $task->delete();
        $workspaceService->refreshProjectRollup($project);

        return $this->respondNoContent();
    }

    private function mapTask(ProjectTask $task, ?User $user = null): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'project_code' => $task->project?->project_code,
            'project_name' => $task->project?->name,
            'task_number' => $task->task_number,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'priority' => $task->priority,
            'stage_id' => $task->stage_id,
            'stage_name' => $task->stage?->name,
            'assigned_to' => $task->assigned_to,
            'assignee_name' => $task->assignee?->name,
            'customer_id' => $task->customer_id,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
            'actual_hours' => (float) $task->actual_hours,
            'is_billable' => (bool) $task->is_billable,
            'billing_status' => $task->billing_status,
            'updated_at' => $task->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $task) ?? false,
            'can_edit' => $user?->can('update', $task) ?? false,
            'can_assign' => $user?->can('assign', $task) ?? false,
        ];
    }
}
