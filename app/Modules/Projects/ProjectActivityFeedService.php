<?php

namespace App\Modules\Projects;

use App\Core\Audit\Models\AuditLog;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\Models\ProjectMilestone;
use App\Modules\Projects\Models\ProjectRecurringBilling;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;

class ProjectActivityFeedService
{
    /**
     * @return array<int, array{
     *     id: string,
     *     action: string,
     *     action_label: string,
     *     subject_type: string,
     *     subject_label: string,
     *     actor_name: string,
     *     summary: string,
     *     changed_fields: array<int, string>,
     *     created_at: string|null
     * }>
     */
    public function timeline(Project $project, int $limit = 25): array
    {
        $taskIds = $project->tasks()->pluck('id')->all();
        $timesheetIds = $project->timesheets()->pluck('id')->all();
        $milestoneIds = $project->milestones()->pluck('id')->all();
        $billableIds = $project->billables()->pluck('id')->all();
        $recurringIds = $project->recurringBillings()->pluck('id')->all();

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->where('company_id', $project->company_id)
            ->where(function ($builder) use (
                $project,
                $taskIds,
                $timesheetIds,
                $milestoneIds,
                $billableIds,
                $recurringIds
            ): void {
                $builder->where(function ($query) use ($project): void {
                    $query
                        ->where('auditable_type', Project::class)
                        ->where('auditable_id', $project->id);
                });

                if ($taskIds !== []) {
                    $builder->orWhere(function ($query) use ($taskIds): void {
                        $query
                            ->where('auditable_type', ProjectTask::class)
                            ->whereIn('auditable_id', $taskIds);
                    });
                }

                if ($timesheetIds !== []) {
                    $builder->orWhere(function ($query) use ($timesheetIds): void {
                        $query
                            ->where('auditable_type', ProjectTimesheet::class)
                            ->whereIn('auditable_id', $timesheetIds);
                    });
                }

                if ($milestoneIds !== []) {
                    $builder->orWhere(function ($query) use ($milestoneIds): void {
                        $query
                            ->where('auditable_type', ProjectMilestone::class)
                            ->whereIn('auditable_id', $milestoneIds);
                    });
                }

                if ($billableIds !== []) {
                    $builder->orWhere(function ($query) use ($billableIds): void {
                        $query
                            ->where('auditable_type', ProjectBillable::class)
                            ->whereIn('auditable_id', $billableIds);
                    });
                }

                if ($recurringIds !== []) {
                    $builder->orWhere(function ($query) use ($recurringIds): void {
                        $query
                            ->where('auditable_type', ProjectRecurringBilling::class)
                            ->whereIn('auditable_id', $recurringIds);
                    });
                }
            })
            ->latest('created_at')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return $logs
            ->map(fn (AuditLog $log) => $this->mapLog($log, $project))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: string,
     *     action: string,
     *     action_label: string,
     *     subject_type: string,
     *     subject_label: string,
     *     actor_name: string,
     *     summary: string,
     *     changed_fields: array<int, string>,
     *     created_at: string|null
     * }
     */
    private function mapLog(AuditLog $log, Project $project): array
    {
        $changes = is_array($log->changes) ? $log->changes : [];
        $after = is_array($changes['after'] ?? null) ? $changes['after'] : [];
        $before = is_array($changes['before'] ?? null) ? $changes['before'] : [];
        $fields = array_values(array_unique([
            ...array_keys($before),
            ...array_keys($after),
        ]));
        $subjectType = $this->subjectTypeLabel((string) $log->auditable_type);
        $subjectLabel = $this->subjectLabel($log, $project, $after, $before);

        return [
            'id' => (string) $log->id,
            'action' => (string) $log->action,
            'action_label' => str_replace('_', ' ', ucfirst((string) $log->action)),
            'subject_type' => $subjectType,
            'subject_label' => $subjectLabel,
            'actor_name' => (string) ($log->actor?->name ?? 'System'),
            'summary' => $this->summary($log, $subjectType, $subjectLabel, $fields),
            'changed_fields' => $fields,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function subjectTypeLabel(string $auditableType): string
    {
        return match ($auditableType) {
            ProjectTask::class => 'Task',
            ProjectTimesheet::class => 'Timesheet',
            ProjectMilestone::class => 'Milestone',
            ProjectBillable::class => 'Billable',
            ProjectRecurringBilling::class => 'Recurring billing',
            default => 'Project',
        };
    }

    /**
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $before
     */
    private function subjectLabel(
        AuditLog $log,
        Project $project,
        array $after,
        array $before
    ): string {
        return match ((string) $log->auditable_type) {
            ProjectTask::class => (string) ($after['task_number']
                ?? $before['task_number']
                ?? $after['title']
                ?? $before['title']
                ?? 'Task '.$log->auditable_id),
            ProjectTimesheet::class => (string) ($after['work_date']
                ?? $before['work_date']
                ?? $after['description']
                ?? $before['description']
                ?? 'Timesheet '.$log->auditable_id),
            ProjectMilestone::class => (string) ($after['name']
                ?? $before['name']
                ?? 'Milestone '.$log->auditable_id),
            ProjectBillable::class => (string) ($after['description']
                ?? $before['description']
                ?? 'Billable '.$log->auditable_id),
            ProjectRecurringBilling::class => (string) ($after['name']
                ?? $before['name']
                ?? 'Recurring schedule '.$log->auditable_id),
            default => (string) ($after['file_name']
                ?? $before['file_name']
                ?? $after['name']
                ?? $before['name']
                ?? $project->project_code),
        };
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function summary(
        AuditLog $log,
        string $subjectType,
        string $subjectLabel,
        array $fields
    ): string {
        if ((string) $log->action === 'file_uploaded') {
            return $subjectType.' file uploaded: '.$subjectLabel.'.';
        }

        if ((string) $log->action === 'file_deleted') {
            return $subjectType.' file removed: '.$subjectLabel.'.';
        }

        if ((string) $log->action === 'updated' && $fields !== []) {
            return sprintf(
                '%s %s updated (%s).',
                $subjectType,
                $subjectLabel,
                implode(', ', array_slice($fields, 0, 3)),
            );
        }

        return sprintf(
            '%s %s %s.',
            $subjectType,
            $subjectLabel,
            strtolower(str_replace('_', ' ', (string) $log->action)),
        );
    }
}
