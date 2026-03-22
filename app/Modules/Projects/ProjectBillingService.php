<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectTimesheet;
use Illuminate\Support\Facades\DB;

class ProjectBillingService
{
    public function __construct(
        private readonly ProjectWorkspaceService $workspaceService,
    ) {}

    public function syncFromTimesheet(
        ProjectTimesheet $timesheet,
        ?string $actorId = null,
    ): ?ProjectBillable {
        return DB::transaction(function () use ($timesheet, $actorId) {
            $timesheet = ProjectTimesheet::query()
                ->with(['project', 'task'])
                ->lockForUpdate()
                ->findOrFail($timesheet->id);

            if (! $this->timesheetQualifiesForBillable($timesheet)) {
                $this->cancelBillableForSource($timesheet, $actorId);

                return null;
            }

            $project = $timesheet->project;
            $billable = $this->resolveBillableForSource($timesheet);

            $billable->forceFill([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'billable_type' => ProjectBillable::TYPE_TIMESHEET,
                'source_type' => $timesheet::class,
                'source_id' => $timesheet->id,
                'customer_id' => $project->customer_id,
                'description' => $this->timesheetDescription($timesheet),
                'quantity' => round((float) $timesheet->hours, 4),
                'unit_price' => round((float) $timesheet->bill_rate, 2),
                'amount' => round((float) $timesheet->billable_amount, 2),
                'currency_id' => $project->currency_id,
                'status' => ProjectBillable::STATUS_READY,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $actorId,
            ]);

            if (! $billable->exists) {
                $billable->created_by = $actorId;
            }

            $billable->saveQuietly();

            $this->workspaceService->refreshProjectRollup($project);

            return $billable->fresh(['project', 'customer', 'currency']) ?? $billable;
        });
    }

    public function syncFromMilestone(
        ProjectMilestone $milestone,
        ?string $actorId = null,
    ): ?ProjectBillable {
        return DB::transaction(function () use ($milestone, $actorId) {
            $milestone = ProjectMilestone::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($milestone->id);

            if (! $this->milestoneQualifiesForBillable($milestone)) {
                $this->cancelBillableForSource($milestone, $actorId);

                return null;
            }

            $project = $milestone->project;
            $billable = $this->resolveBillableForSource($milestone);

            $billable->forceFill([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'billable_type' => ProjectBillable::TYPE_MILESTONE,
                'source_type' => $milestone::class,
                'source_id' => $milestone->id,
                'customer_id' => $project->customer_id,
                'description' => $this->milestoneDescription($milestone),
                'quantity' => 1,
                'unit_price' => round((float) $milestone->amount, 2),
                'amount' => round((float) $milestone->amount, 2),
                'currency_id' => $project->currency_id,
                'status' => ProjectBillable::STATUS_READY,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $actorId,
            ]);

            if (! $billable->exists) {
                $billable->created_by = $actorId;
            }

            $billable->saveQuietly();

            $this->workspaceService->refreshProjectRollup($project);

            return $billable->fresh(['project', 'customer', 'currency']) ?? $billable;
        });
    }

    public function cancelBillableForSource(
        ProjectTimesheet|ProjectMilestone $source,
        ?string $actorId = null,
    ): void {
        DB::transaction(function () use ($source, $actorId) {
            $billable = ProjectBillable::query()
                ->withTrashed()
                ->where('source_type', $source::class)
                ->where('source_id', $source->id)
                ->first();

            if (! $billable) {
                return;
            }

            if ($billable->invoice_id || $billable->status === ProjectBillable::STATUS_INVOICED) {
                return;
            }

            $billable->update([
                'status' => ProjectBillable::STATUS_CANCELLED,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
                'approved_by' => null,
                'approved_at' => null,
                'updated_by' => $actorId,
            ]);

            $project = $source->project;

            if ($project instanceof Project) {
                $this->workspaceService->refreshProjectRollup($project);
            }
        });
    }

    private function timesheetQualifiesForBillable(ProjectTimesheet $timesheet): bool
    {
        return $timesheet->approval_status === ProjectTimesheet::APPROVAL_STATUS_APPROVED
            && $timesheet->is_billable
            && round((float) $timesheet->billable_amount, 2) > 0;
    }

    private function milestoneQualifiesForBillable(ProjectMilestone $milestone): bool
    {
        return $milestone->status === ProjectMilestone::STATUS_APPROVED
            && round((float) $milestone->amount, 2) > 0;
    }

    private function resolveBillableForSource(
        ProjectTimesheet|ProjectMilestone $source,
    ): ProjectBillable {
        $billable = ProjectBillable::query()
            ->withTrashed()
            ->firstOrNew([
                'source_type' => $source::class,
                'source_id' => $source->id,
            ]);

        if ($billable->trashed()) {
            $billable->restore();
        }

        return $billable;
    }

    private function timesheetDescription(ProjectTimesheet $timesheet): string
    {
        if (filled($timesheet->description)) {
            return trim((string) $timesheet->description);
        }

        if ($timesheet->task) {
            return trim(sprintf(
                '%s - %s',
                (string) $timesheet->task->task_number,
                (string) $timesheet->task->title,
            ));
        }

        return 'Project timesheet entry';
    }

    private function milestoneDescription(ProjectMilestone $milestone): string
    {
        return trim(sprintf(
            'Milestone %s: %s',
            str_pad((string) $milestone->sequence, 2, '0', STR_PAD_LEFT),
            (string) $milestone->name,
        ));
    }
}
