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
        private readonly ProjectBillableApprovalPolicyService $approvalPolicyService,
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
            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                (string) $project->company_id,
                round((float) $timesheet->billable_amount, 2),
            );
            $basePayload = [
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
            ];

            $billable->forceFill([
                ...$basePayload,
                ...$this->decisionStatePayload(
                    billable: $billable,
                    requiresApproval: $requiresApproval,
                    basePayload: $basePayload,
                ),
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
            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                (string) $project->company_id,
                round((float) $milestone->amount, 2),
            );
            $basePayload = [
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
            ];

            $billable->forceFill([
                ...$basePayload,
                ...$this->decisionStatePayload(
                    billable: $billable,
                    requiresApproval: $requiresApproval,
                    basePayload: $basePayload,
                ),
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
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
                'cancellation_reason' => 'Source record no longer qualifies for billing.',
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

    /**
     * @param  array{
     *     company_id: string,
     *     project_id: string,
     *     billable_type: string,
     *     source_type: string,
     *     source_id: string,
     *     customer_id: string|null,
     *     description: string,
     *     quantity: int|float,
     *     unit_price: int|float,
     *     amount: int|float,
     *     currency_id: string|null
     * }  $basePayload
     * @return array<string, mixed>
     */
    private function decisionStatePayload(
        ProjectBillable $billable,
        bool $requiresApproval,
        array $basePayload,
    ): array {
        $payloadChanged = $this->payloadChanged($billable, $basePayload);

        if (
            $billable->exists
            && ! $payloadChanged
            && $billable->approval_status === ProjectBillable::APPROVAL_STATUS_APPROVED
            && $billable->status === ProjectBillable::STATUS_APPROVED
        ) {
            return [
                'status' => ProjectBillable::STATUS_APPROVED,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_APPROVED,
                'approved_by' => $billable->approved_by,
                'approved_at' => $billable->approved_at,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ];
        }

        if (
            $billable->exists
            && ! $payloadChanged
            && $billable->approval_status === ProjectBillable::APPROVAL_STATUS_REJECTED
            && $requiresApproval
        ) {
            return [
                'status' => ProjectBillable::STATUS_READY,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => $billable->rejected_by,
                'rejected_at' => $billable->rejected_at,
                'rejection_reason' => $billable->rejection_reason,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
            ];
        }

        return [
            'status' => ProjectBillable::STATUS_READY,
            'approval_status' => $requiresApproval
                ? ProjectBillable::APPROVAL_STATUS_PENDING
                : ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ];
    }

    /**
     * @param  array{
     *     company_id: string,
     *     project_id: string,
     *     billable_type: string,
     *     source_type: string,
     *     source_id: string,
     *     customer_id: string|null,
     *     description: string,
     *     quantity: int|float,
     *     unit_price: int|float,
     *     amount: int|float,
     *     currency_id: string|null
     * }  $basePayload
     */
    private function payloadChanged(ProjectBillable $billable, array $basePayload): bool
    {
        if (! $billable->exists) {
            return true;
        }

        return (string) $billable->project_id !== (string) $basePayload['project_id']
            || (string) $billable->billable_type !== (string) $basePayload['billable_type']
            || (string) $billable->customer_id !== (string) $basePayload['customer_id']
            || trim((string) $billable->description) !== trim((string) $basePayload['description'])
            || round((float) $billable->quantity, 4) !== round((float) $basePayload['quantity'], 4)
            || round((float) $billable->unit_price, 2) !== round((float) $basePayload['unit_price'], 2)
            || round((float) $billable->amount, 2) !== round((float) $basePayload['amount'], 2)
            || (string) $billable->currency_id !== (string) $basePayload['currency_id'];
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
