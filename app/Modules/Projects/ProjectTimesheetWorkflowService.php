<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use Illuminate\Support\Facades\DB;

class ProjectTimesheetWorkflowService
{
    public function __construct(
        private readonly ProjectWorkspaceService $workspaceService,
        private readonly ProjectBillingService $billingService,
    ) {}

    /**
     * @param  array{
     *     user_id?: string|null,
     *     task_id?: string|null,
     *     work_date: string,
     *     description?: string|null,
     *     hours: int|float|string,
     *     is_billable: bool,
     *     cost_rate?: int|float|string|null,
     *     bill_rate?: int|float|string|null
     * }  $attributes
     */
    public function createDraft(
        Project $project,
        array $attributes,
        bool $canManageTeamTimesheets = false,
        ?string $actorId = null,
    ): ProjectTimesheet {
        return DB::transaction(function () use (
            $project,
            $attributes,
            $canManageTeamTimesheets,
            $actorId,
        ) {
            $userId = $this->resolveTargetUserId(
                attributes: $attributes,
                canManageTeamTimesheets: $canManageTeamTimesheets,
                actorId: $actorId,
            );

            $task = $this->resolveTask($project, $attributes['task_id'] ?? null);
            $projectMember = $this->resolveProjectMember($project, $userId);
            $timesheetPayload = $this->buildTimesheetPayload(
                project: $project,
                projectMember: $projectMember,
                attributes: $attributes,
                userId: $userId,
                canManageTeamTimesheets: $canManageTeamTimesheets,
            );

            $timesheet = ProjectTimesheet::create([
                ...$timesheetPayload,
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $this->workspaceService->syncTaskAssigneeMember(
                project: $project,
                assigneeId: $userId,
                actorId: $actorId,
            );

            if ($task) {
                $this->workspaceService->refreshTaskActualHours($task);
            }

            $this->workspaceService->refreshProjectRollup($project);

            return $timesheet->fresh(['project', 'task', 'user']) ?? $timesheet;
        });
    }

    /**
     * @param  array{
     *     user_id?: string|null,
     *     task_id?: string|null,
     *     work_date: string,
     *     description?: string|null,
     *     hours: int|float|string,
     *     is_billable: bool,
     *     cost_rate?: int|float|string|null,
     *     bill_rate?: int|float|string|null
     * }  $attributes
     */
    public function updateDraft(
        ProjectTimesheet $timesheet,
        array $attributes,
        bool $canManageTeamTimesheets = false,
        ?string $actorId = null,
    ): ProjectTimesheet {
        return DB::transaction(function () use (
            $timesheet,
            $attributes,
            $canManageTeamTimesheets,
            $actorId,
        ) {
            $timesheet = ProjectTimesheet::query()
                ->with(['project', 'task'])
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            if (! in_array($timesheet->approval_status, [
                ProjectTimesheet::APPROVAL_STATUS_DRAFT,
                ProjectTimesheet::APPROVAL_STATUS_REJECTED,
            ], true)) {
                abort(422, 'Only draft or rejected timesheets can be updated.');
            }

            $userId = $this->resolveTargetUserId(
                attributes: $attributes,
                canManageTeamTimesheets: $canManageTeamTimesheets,
                actorId: $actorId,
                fallbackUserId: (string) $timesheet->user_id,
            );

            $task = $this->resolveTask(
                $timesheet->project,
                $attributes['task_id'] ?? null,
            );
            $projectMember = $this->resolveProjectMember($timesheet->project, $userId);
            $timesheetPayload = $this->buildTimesheetPayload(
                project: $timesheet->project,
                projectMember: $projectMember,
                attributes: $attributes,
                userId: $userId,
                canManageTeamTimesheets: $canManageTeamTimesheets,
            );

            $previousTask = $timesheet->task;

            $timesheet->update([
                ...$timesheetPayload,
                'task_id' => $task?->id,
                'approval_status' => ProjectTimesheet::APPROVAL_STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            $this->workspaceService->syncTaskAssigneeMember(
                project: $timesheet->project,
                assigneeId: $userId,
                actorId: $actorId,
            );

            if ($previousTask) {
                $this->workspaceService->refreshTaskActualHours($previousTask);
            }

            if ($task && (string) $previousTask?->id !== (string) $task->id) {
                $this->workspaceService->refreshTaskActualHours($task);
            }

            $this->workspaceService->refreshProjectRollup($timesheet->project);

            return $timesheet->fresh(['project', 'task', 'user']) ?? $timesheet;
        });
    }

    public function submit(ProjectTimesheet $timesheet, ?string $actorId = null): ProjectTimesheet
    {
        return DB::transaction(function () use ($timesheet, $actorId) {
            $timesheet = ProjectTimesheet::query()
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            if (! in_array($timesheet->approval_status, [
                ProjectTimesheet::APPROVAL_STATUS_DRAFT,
                ProjectTimesheet::APPROVAL_STATUS_REJECTED,
            ], true)) {
                abort(422, 'Only draft or rejected timesheets can be submitted.');
            }

            $timesheet->update([
                'approval_status' => ProjectTimesheet::APPROVAL_STATUS_SUBMITTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
                'invoice_status' => $timesheet->is_billable
                    ? ProjectTimesheet::INVOICE_STATUS_NOT_READY
                    : ProjectTimesheet::INVOICE_STATUS_NON_BILLABLE,
                'updated_by' => $actorId,
            ]);

            return $timesheet->fresh(['project', 'task', 'user']) ?? $timesheet;
        });
    }

    public function approve(ProjectTimesheet $timesheet, ?string $actorId = null): ProjectTimesheet
    {
        return DB::transaction(function () use ($timesheet, $actorId) {
            $timesheet = ProjectTimesheet::query()
                ->with(['project', 'task'])
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            if ($timesheet->approval_status !== ProjectTimesheet::APPROVAL_STATUS_SUBMITTED) {
                abort(422, 'Only submitted timesheets can be approved.');
            }

            $timesheet->update([
                'approval_status' => ProjectTimesheet::APPROVAL_STATUS_APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'rejection_reason' => null,
                'invoice_status' => $timesheet->is_billable
                    ? ProjectTimesheet::INVOICE_STATUS_READY
                    : ProjectTimesheet::INVOICE_STATUS_NON_BILLABLE,
                'updated_by' => $actorId,
            ]);

            $this->billingService->syncFromTimesheet($timesheet, $actorId);

            return $timesheet->fresh(['project', 'task', 'user']) ?? $timesheet;
        });
    }

    public function reject(
        ProjectTimesheet $timesheet,
        ?string $reason = null,
        ?string $actorId = null,
    ): ProjectTimesheet {
        return DB::transaction(function () use ($timesheet, $reason, $actorId) {
            $timesheet = ProjectTimesheet::query()
                ->with(['project', 'task'])
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            if ($timesheet->approval_status !== ProjectTimesheet::APPROVAL_STATUS_SUBMITTED) {
                abort(422, 'Only submitted timesheets can be rejected.');
            }

            $timesheet->update([
                'approval_status' => ProjectTimesheet::APPROVAL_STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => filled($reason) ? trim((string) $reason) : null,
                'invoice_status' => $timesheet->is_billable
                    ? ProjectTimesheet::INVOICE_STATUS_NOT_READY
                    : ProjectTimesheet::INVOICE_STATUS_NON_BILLABLE,
                'updated_by' => $actorId,
            ]);

            $this->billingService->cancelBillableForSource($timesheet, $actorId);

            return $timesheet->fresh(['project', 'task', 'user']) ?? $timesheet;
        });
    }

    public function delete(ProjectTimesheet $timesheet): void
    {
        DB::transaction(function () use ($timesheet) {
            $timesheet = ProjectTimesheet::query()
                ->with(['project', 'task'])
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            $task = $timesheet->task;
            $project = $timesheet->project;

            $this->billingService->cancelBillableForSource($timesheet);
            $timesheet->delete();

            if ($task) {
                $this->workspaceService->refreshTaskActualHours($task);
            }

            $this->workspaceService->refreshProjectRollup($project);
        });
    }

    /**
     * @param  array{
     *     user_id?: string|null,
     *     task_id?: string|null,
     *     work_date: string,
     *     description?: string|null,
     *     hours: int|float|string,
     *     is_billable: bool,
     *     cost_rate?: int|float|string|null,
     *     bill_rate?: int|float|string|null
     * }  $attributes
     * @return array{
     *     user_id: string,
     *     work_date: string,
     *     description: string|null,
     *     hours: float,
     *     is_billable: bool,
     *     cost_rate: float,
     *     bill_rate: float,
     *     cost_amount: float,
     *     billable_amount: float,
     *     invoice_status: string
     * }
     */
    private function buildTimesheetPayload(
        Project $project,
        ?ProjectMember $projectMember,
        array $attributes,
        string $userId,
        bool $canManageTeamTimesheets = false,
    ): array {
        $hours = round((float) $attributes['hours'], 2);
        $isBillable = (bool) $attributes['is_billable'];
        $defaultCostRate = round((float) ($projectMember?->hourly_cost_rate ?? 0), 2);
        $defaultBillRate = round((float) ($projectMember?->hourly_bill_rate ?? 0), 2);
        $costRate = $canManageTeamTimesheets && array_key_exists('cost_rate', $attributes)
            ? round((float) ($attributes['cost_rate'] ?? 0), 2)
            : $defaultCostRate;
        $billRate = $isBillable
            ? ($canManageTeamTimesheets && array_key_exists('bill_rate', $attributes)
                ? round((float) ($attributes['bill_rate'] ?? 0), 2)
                : $defaultBillRate)
            : 0.0;

        return [
            'user_id' => $userId,
            'work_date' => (string) $attributes['work_date'],
            'description' => filled($attributes['description'] ?? null)
                ? trim((string) $attributes['description'])
                : null,
            'hours' => $hours,
            'is_billable' => $isBillable,
            'cost_rate' => $costRate,
            'bill_rate' => $billRate,
            'cost_amount' => round($hours * $costRate, 2),
            'billable_amount' => $isBillable ? round($hours * $billRate, 2) : 0.0,
            'invoice_status' => $isBillable
                ? ProjectTimesheet::INVOICE_STATUS_NOT_READY
                : ProjectTimesheet::INVOICE_STATUS_NON_BILLABLE,
        ];
    }

    /**
     * @param  array{user_id?: string|null}  $attributes
     */
    private function resolveTargetUserId(
        array $attributes,
        bool $canManageTeamTimesheets,
        ?string $actorId,
        ?string $fallbackUserId = null,
    ): string {
        $userId = $canManageTeamTimesheets
            ? ($attributes['user_id'] ?? $fallbackUserId ?? $actorId)
            : ($actorId ?? $fallbackUserId);

        if (! $userId) {
            abort(422, 'A timesheet owner is required.');
        }

        return (string) $userId;
    }

    private function resolveTask(Project $project, ?string $taskId): ?ProjectTask
    {
        if (! $taskId) {
            return null;
        }

        return ProjectTask::query()
            ->where('project_id', $project->id)
            ->findOrFail($taskId);
    }

    private function resolveProjectMember(Project $project, string $userId): ?ProjectMember
    {
        return ProjectMember::query()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->first();
    }
}
