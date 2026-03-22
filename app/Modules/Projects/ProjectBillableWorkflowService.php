<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\ProjectBillable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectBillableWorkflowService
{
    public function approve(ProjectBillable $billable, ?string $actorId = null): ProjectBillable
    {
        return DB::transaction(function () use ($billable, $actorId) {
            $billable = ProjectBillable::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($billable->id);

            $this->ensureDecisionAllowed($billable);

            if ($billable->approval_status === ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED) {
                throw ValidationException::withMessages([
                    'approval' => 'This billable does not require approval.',
                ]);
            }

            $billable->update([
                'status' => ProjectBillable::STATUS_APPROVED,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'updated_by' => $actorId,
            ]);

            return $billable->fresh(['project', 'approvedBy']) ?? $billable;
        });
    }

    public function reject(
        ProjectBillable $billable,
        ?string $reason = null,
        ?string $actorId = null,
    ): ProjectBillable {
        return DB::transaction(function () use ($billable, $reason, $actorId) {
            $billable = ProjectBillable::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($billable->id);

            $this->ensureDecisionAllowed($billable);

            if ($billable->approval_status === ProjectBillable::APPROVAL_STATUS_NOT_REQUIRED) {
                throw ValidationException::withMessages([
                    'approval' => 'Only approval-controlled billables can be rejected.',
                ]);
            }

            $billable->update([
                'status' => ProjectBillable::STATUS_READY,
                'approval_status' => ProjectBillable::APPROVAL_STATUS_REJECTED,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => $actorId,
                'rejected_at' => now(),
                'rejection_reason' => filled($reason) ? trim((string) $reason) : null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'updated_by' => $actorId,
            ]);

            return $billable->fresh(['project', 'rejectedBy']) ?? $billable;
        });
    }

    public function cancel(
        ProjectBillable $billable,
        ?string $reason = null,
        ?string $actorId = null,
    ): ProjectBillable {
        return DB::transaction(function () use ($billable, $reason, $actorId) {
            $billable = ProjectBillable::query()
                ->with('project')
                ->lockForUpdate()
                ->findOrFail($billable->id);

            $this->ensureDecisionAllowed($billable);

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
                'cancellation_reason' => filled($reason) ? trim((string) $reason) : null,
                'updated_by' => $actorId,
            ]);

            return $billable->fresh(['project', 'cancelledBy']) ?? $billable;
        });
    }

    private function ensureDecisionAllowed(ProjectBillable $billable): void
    {
        if ($billable->invoice_id || $billable->status === ProjectBillable::STATUS_INVOICED) {
            throw ValidationException::withMessages([
                'approval' => 'Invoiced billables cannot be changed.',
            ]);
        }

        if ($billable->status === ProjectBillable::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'approval' => 'Cancelled billables cannot be processed further.',
            ]);
        }
    }
}
