<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMilestone;
use Illuminate\Support\Facades\DB;

class ProjectMilestoneWorkflowService
{
    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     sequence?: int|string|null,
     *     status: string,
     *     due_date?: string|null,
     *     amount?: int|float|string|null
     * }  $attributes
     */
    public function create(
        Project $project,
        array $attributes,
        ?string $actorId = null,
    ): ProjectMilestone {
        return DB::transaction(function () use ($project, $attributes, $actorId) {
            $sequence = $this->resolveSequence($project, $attributes['sequence'] ?? null);
            $status = (string) $attributes['status'];
            $amount = round((float) ($attributes['amount'] ?? 0), 2);

            $milestone = ProjectMilestone::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'name' => trim((string) $attributes['name']),
                'description' => filled($attributes['description'] ?? null)
                    ? trim((string) $attributes['description'])
                    : null,
                'sequence' => $sequence,
                'status' => $status,
                'due_date' => $attributes['due_date'] ?? null,
                'completed_at' => $this->completedAtForStatus($status),
                'approved_by' => $this->approvedByForStatus($status, $actorId),
                'approved_at' => $this->approvedAtForStatus($status),
                'amount' => $amount,
                'invoice_status' => $this->invoiceStatusFor($status, $amount),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $milestone->fresh(['project', 'approvedBy']) ?? $milestone;
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     description?: string|null,
     *     sequence?: int|string|null,
     *     status: string,
     *     due_date?: string|null,
     *     amount?: int|float|string|null
     * }  $attributes
     */
    public function update(
        ProjectMilestone $milestone,
        array $attributes,
        ?string $actorId = null,
    ): ProjectMilestone {
        return DB::transaction(function () use ($milestone, $attributes, $actorId) {
            $milestone = ProjectMilestone::query()
                ->lockForUpdate()
                ->findOrFail($milestone->id);

            $status = (string) $attributes['status'];
            $amount = round((float) ($attributes['amount'] ?? 0), 2);

            $milestone->update([
                'name' => trim((string) $attributes['name']),
                'description' => filled($attributes['description'] ?? null)
                    ? trim((string) $attributes['description'])
                    : null,
                'sequence' => $this->resolveSequence(
                    $milestone->project,
                    $attributes['sequence'] ?? null,
                    $milestone,
                ),
                'status' => $status,
                'due_date' => $attributes['due_date'] ?? null,
                'completed_at' => $this->completedAtForStatus($status),
                'approved_by' => $this->approvedByForStatus($status, $actorId),
                'approved_at' => $this->approvedAtForStatus($status),
                'amount' => $amount,
                'invoice_status' => $this->invoiceStatusFor($status, $amount),
                'updated_by' => $actorId,
            ]);

            return $milestone->fresh(['project', 'approvedBy']) ?? $milestone;
        });
    }

    public function delete(ProjectMilestone $milestone): void
    {
        $milestone->delete();
    }

    private function resolveSequence(
        Project $project,
        int|string|null $sequence = null,
        ?ProjectMilestone $ignoreMilestone = null,
    ): int {
        if ($sequence !== null && $sequence !== '') {
            return max(1, (int) $sequence);
        }

        $lastSequence = ProjectMilestone::query()
            ->where('project_id', $project->id)
            ->when(
                $ignoreMilestone,
                fn ($builder) => $builder->where('id', '!=', $ignoreMilestone?->id),
            )
            ->max('sequence');

        return ((int) $lastSequence) + 1;
    }

    private function completedAtForStatus(string $status): ?string
    {
        return in_array($status, [
            ProjectMilestone::STATUS_READY_FOR_REVIEW,
            ProjectMilestone::STATUS_APPROVED,
        ], true)
            ? now()->toIso8601String()
            : null;
    }

    private function approvedByForStatus(string $status, ?string $actorId = null): ?string
    {
        return $status === ProjectMilestone::STATUS_APPROVED ? $actorId : null;
    }

    private function approvedAtForStatus(string $status): ?string
    {
        return $status === ProjectMilestone::STATUS_APPROVED
            ? now()->toIso8601String()
            : null;
    }

    private function invoiceStatusFor(string $status, float $amount): string
    {
        if ($status === ProjectMilestone::STATUS_APPROVED && $amount > 0) {
            return ProjectMilestone::INVOICE_STATUS_READY;
        }

        return ProjectMilestone::INVOICE_STATUS_NOT_READY;
    }
}
