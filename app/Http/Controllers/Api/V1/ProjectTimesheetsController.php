<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Projects\ProjectTimesheetRejectRequest;
use App\Http\Requests\Projects\ProjectTimesheetStoreRequest;
use App\Http\Requests\Projects\ProjectTimesheetUpdateRequest;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Projects\ProjectTimesheetWorkflowService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTimesheetsController extends ApiController
{
    public function index(Project $project, Request $request): JsonResponse
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', ProjectTimesheet::class);

        $perPage = ApiQuery::perPage($request);
        $approvalStatus = trim((string) $request->input('approval_status', ''));
        $userId = trim((string) $request->input('user_id', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['work_date', 'created_at', 'hours', 'updated_at'],
            defaultSort: 'work_date',
            defaultDirection: 'desc',
        );

        $timesheets = ProjectTimesheet::query()
            ->with(['user:id,name', 'task:id,task_number,title', 'approvedBy:id,name'])
            ->where('project_id', $project->id)
            ->when($approvalStatus !== '', fn ($query) => $query->where('approval_status', $approvalStatus))
            ->when($userId !== '', fn ($query) => $query->where('user_id', $userId))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->when($sort !== 'created_at', fn ($query) => $query->orderBy('created_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $timesheets,
            data: collect($timesheets->items())
                ->map(fn (ProjectTimesheet $timesheet) => $this->mapTimesheet($timesheet, $request->user()))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'approval_status' => $approvalStatus,
                'user_id' => $userId,
            ],
        );
    }

    public function store(
        ProjectTimesheetStoreRequest $request,
        Project $project,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('view', $project);
        $this->authorize('create', ProjectTimesheet::class);

        $timesheet = $workflowService->createDraft(
            project: $project,
            attributes: $request->validated(),
            canManageTeamTimesheets: $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false,
            actorId: $request->user()?->id,
        );

        return $this->respond($this->mapTimesheet($timesheet, $request->user()), 201);
    }

    public function show(ProjectTimesheet $timesheet, Request $request): JsonResponse
    {
        $this->authorize('view', $timesheet);

        return $this->respond(
            $this->mapTimesheet(
                $timesheet->load([
                    'project:id,project_code,name',
                    'task:id,task_number,title',
                    'user:id,name',
                    'approvedBy:id,name',
                ]),
                $request->user(),
            ),
        );
    }

    public function update(
        ProjectTimesheetUpdateRequest $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('update', $timesheet);

        $timesheet = $workflowService->updateDraft(
            timesheet: $timesheet,
            attributes: $request->validated(),
            canManageTeamTimesheets: $request->user()?->hasPermission('projects.timesheets.manage_team') ?? false,
            actorId: $request->user()?->id,
        );

        return $this->respond($this->mapTimesheet($timesheet, $request->user()));
    }

    public function submit(
        Request $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('submit', $timesheet);

        $workflowService->submit($timesheet, $request->user()?->id);

        return $this->respond(
            $this->mapTimesheet(
                $timesheet->fresh(['project:id,project_code,name', 'task:id,task_number,title', 'user:id,name', 'approvedBy:id,name']),
                $request->user(),
            )
        );
    }

    public function approve(
        Request $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('approve', $timesheet);

        $workflowService->approve($timesheet, $request->user()?->id);

        return $this->respond(
            $this->mapTimesheet(
                $timesheet->fresh(['project:id,project_code,name', 'task:id,task_number,title', 'user:id,name', 'approvedBy:id,name']),
                $request->user(),
            )
        );
    }

    public function reject(
        ProjectTimesheetRejectRequest $request,
        ProjectTimesheet $timesheet,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('reject', $timesheet);

        $workflowService->reject(
            timesheet: $timesheet,
            reason: $request->validated('reason'),
            actorId: $request->user()?->id,
        );

        return $this->respond(
            $this->mapTimesheet(
                $timesheet->fresh(['project:id,project_code,name', 'task:id,task_number,title', 'user:id,name', 'approvedBy:id,name']),
                $request->user(),
            )
        );
    }

    public function destroy(
        ProjectTimesheet $timesheet,
        Request $request,
        ProjectTimesheetWorkflowService $workflowService,
    ): JsonResponse {
        $this->authorize('delete', $timesheet);

        $workflowService->delete($timesheet);

        return $this->respondNoContent();
    }

    private function mapTimesheet(ProjectTimesheet $timesheet, ?User $user = null): array
    {
        return [
            'id' => $timesheet->id,
            'project_id' => $timesheet->project_id,
            'project_code' => $timesheet->project?->project_code,
            'project_name' => $timesheet->project?->name,
            'task_id' => $timesheet->task_id,
            'task_number' => $timesheet->task?->task_number,
            'task_title' => $timesheet->task?->title,
            'user_id' => $timesheet->user_id,
            'user_name' => $timesheet->user?->name,
            'work_date' => $timesheet->work_date?->toDateString(),
            'description' => $timesheet->description,
            'hours' => (float) $timesheet->hours,
            'is_billable' => (bool) $timesheet->is_billable,
            'cost_rate' => (float) $timesheet->cost_rate,
            'bill_rate' => (float) $timesheet->bill_rate,
            'cost_amount' => (float) $timesheet->cost_amount,
            'billable_amount' => (float) $timesheet->billable_amount,
            'approval_status' => $timesheet->approval_status,
            'approved_by' => $timesheet->approved_by,
            'approved_by_name' => $timesheet->approvedBy?->name,
            'approved_at' => $timesheet->approved_at?->toIso8601String(),
            'rejection_reason' => $timesheet->rejection_reason,
            'invoice_status' => $timesheet->invoice_status,
            'updated_at' => $timesheet->updated_at?->toIso8601String(),
            'can_view' => $user?->can('view', $timesheet) ?? false,
            'can_edit' => $user?->can('update', $timesheet) ?? false,
            'can_submit' => $user?->can('submit', $timesheet) ?? false,
            'can_approve' => $user?->can('approve', $timesheet) ?? false,
            'can_reject' => $user?->can('reject', $timesheet) ?? false,
        ];
    }
}
