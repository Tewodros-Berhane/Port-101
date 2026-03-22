<?php

namespace App\Modules\Projects;

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Projects\Models\ProjectStage;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use Illuminate\Database\Eloquent\Collection;

class ProjectWorkspaceService
{
    /**
     * @var array<int, array{name: string, color: string, is_closed_stage: bool}>
     */
    private const DEFAULT_STAGES = [
        [
            'name' => 'Backlog',
            'color' => 'slate',
            'is_closed_stage' => false,
        ],
        [
            'name' => 'In Progress',
            'color' => 'blue',
            'is_closed_stage' => false,
        ],
        [
            'name' => 'Review',
            'color' => 'amber',
            'is_closed_stage' => false,
        ],
        [
            'name' => 'Done',
            'color' => 'emerald',
            'is_closed_stage' => true,
        ],
    ];

    public function ensureDefaultStages(
        string $companyId,
        ?string $actorId = null
    ): Collection {
        $stages = ProjectStage::query()
            ->where('company_id', $companyId)
            ->orderBy('sequence')
            ->get();

        if ($stages->isNotEmpty()) {
            return $stages;
        }

        foreach (self::DEFAULT_STAGES as $index => $stage) {
            ProjectStage::create([
                'company_id' => $companyId,
                'name' => $stage['name'],
                'sequence' => $index + 1,
                'color' => $stage['color'],
                'is_closed_stage' => $stage['is_closed_stage'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        return ProjectStage::query()
            ->where('company_id', $companyId)
            ->orderBy('sequence')
            ->get();
    }

    public function syncProjectMembers(Project $project, ?string $actorId = null): void
    {
        $managerId = $project->project_manager_id
            ? (string) $project->project_manager_id
            : null;

        if ($managerId) {
            $this->upsertProjectMember(
                project: $project,
                userId: $managerId,
                projectRole: ProjectMember::ROLE_MANAGER,
                actorId: $actorId,
            );

            ProjectMember::query()
                ->withTrashed()
                ->where('project_id', $project->id)
                ->where('project_role', ProjectMember::ROLE_MANAGER)
                ->where('user_id', '!=', $managerId)
                ->get()
                ->each(function (ProjectMember $member) use ($actorId): void {
                    $member->forceFill([
                        'project_role' => ProjectMember::ROLE_MEMBER,
                        'updated_by' => $actorId,
                    ])->saveQuietly();
                });
        }

        $creatorId = $project->created_by ? (string) $project->created_by : null;

        if ($creatorId && $creatorId !== $managerId) {
            $this->upsertProjectMember(
                project: $project,
                userId: $creatorId,
                projectRole: ProjectMember::ROLE_MEMBER,
                actorId: $actorId,
            );
        }
    }

    public function syncTaskAssigneeMember(
        Project $project,
        ?string $assigneeId,
        ?string $actorId = null
    ): void {
        if (! $assigneeId) {
            return;
        }

        $member = ProjectMember::query()
            ->withTrashed()
            ->where('project_id', $project->id)
            ->where('user_id', $assigneeId)
            ->first();

        if ($member?->project_role === ProjectMember::ROLE_MANAGER) {
            if ($member->trashed()) {
                $member->restore();
            }

            return;
        }

        $this->upsertProjectMember(
            project: $project,
            userId: $assigneeId,
            projectRole: ProjectMember::ROLE_MEMBER,
            actorId: $actorId,
        );
    }

    public function refreshProjectRollup(Project $project): Project
    {
        $totalTasks = ProjectTask::query()
            ->where('project_id', $project->id)
            ->count();

        $completedTasks = ProjectTask::query()
            ->where('project_id', $project->id)
            ->where('status', ProjectTask::STATUS_DONE)
            ->count();

        $progressPercent = $totalTasks === 0
            ? 0
            : round(($completedTasks / $totalTasks) * 100, 2);

        $project->forceFill([
            'progress_percent' => $progressPercent,
            'actual_cost_amount' => round(
                (float) ProjectTimesheet::query()
                    ->where('project_id', $project->id)
                    ->sum('cost_amount'),
                2,
            ),
            'actual_billable_amount' => round(
                (float) ProjectBillable::query()
                    ->where('project_id', $project->id)
                    ->where('status', '!=', ProjectBillable::STATUS_CANCELLED)
                    ->sum('amount'),
                2,
            ),
        ])->saveQuietly();

        return $project->fresh() ?? $project;
    }

    public function refreshTaskActualHours(ProjectTask $task): ProjectTask
    {
        $task->forceFill([
            'actual_hours' => round(
                (float) ProjectTimesheet::query()
                    ->where('task_id', $task->id)
                    ->sum('hours'),
                2,
            ),
        ])->saveQuietly();

        return $task->fresh() ?? $task;
    }

    private function upsertProjectMember(
        Project $project,
        string $userId,
        string $projectRole,
        ?string $actorId = null
    ): void {
        $member = ProjectMember::query()
            ->withTrashed()
            ->firstOrNew([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'user_id' => $userId,
            ]);

        $member->forceFill([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'user_id' => $userId,
            'project_role' => $projectRole,
            'created_by' => $member->exists ? $member->created_by : $actorId,
            'updated_by' => $actorId,
        ])->saveQuietly();

        if ($member->trashed()) {
            $member->restore();
        }
    }
}
