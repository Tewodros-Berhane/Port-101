<?php

namespace App\Modules\Projects;

use App\Core\Notifications\NotificationGovernanceService;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectRecurringBillingRun;
use App\Modules\Projects\Models\ProjectTask;
use App\Modules\Projects\Models\ProjectTimesheet;
use App\Modules\Sales\Models\SalesOrder;
use App\Notifications\ProjectActivityNotification;

class ProjectNotificationService
{
    public function __construct(
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    public function notifyProjectProvisioned(
        Project $project,
        SalesOrder $order,
        ?string $actorId = null
    ): void {
        $this->sendToUserIds(
            userIds: array_filter([(string) ($project->project_manager_id ?? '')]),
            notification: new ProjectActivityNotification(
                title: 'Project provisioned',
                message: sprintf(
                    'A project workspace was provisioned from sales order %s.',
                    $order->order_number,
                ),
                url: '/company/projects/'.$project->id,
                severity: 'medium',
                meta: [
                    'project_id' => $project->id,
                    'project_code' => $project->project_code,
                    'sales_order_id' => $order->id,
                    'sales_order_number' => $order->order_number,
                ],
            ),
            severity: 'medium',
            event: 'Project provisioned',
            source: 'projects.sales_provisioning',
            actorId: $actorId,
        );
    }

    public function notifyTaskAssigned(
        ProjectTask $task,
        ?string $previousAssigneeId = null,
        ?string $actorId = null
    ): void {
        $task->loadMissing('project:id,id,project_code', 'assignee:id,name');

        if (! $task->assigned_to || (string) $task->assigned_to === (string) $previousAssigneeId) {
            return;
        }

        $actorName = $this->actorName($actorId);

        $this->sendToUserIds(
            userIds: [(string) $task->assigned_to],
            notification: new ProjectActivityNotification(
                title: 'Task assigned',
                message: sprintf(
                    '%s assigned %s in %s to you.',
                    $actorName,
                    $task->task_number,
                    $task->project?->project_code ?? 'the project',
                ),
                url: '/company/projects/tasks/'.$task->id.'/edit',
                severity: 'medium',
                meta: [
                    'project_id' => $task->project_id,
                    'task_id' => $task->id,
                    'task_number' => $task->task_number,
                ],
            ),
            severity: 'medium',
            event: 'Project task assigned',
            source: 'projects.tasks',
            actorId: $actorId,
        );
    }

    public function notifyTimesheetSubmitted(
        ProjectTimesheet $timesheet,
        ?string $actorId = null
    ): void {
        $timesheet->loadMissing('project:id,id,project_code,project_manager_id', 'user:id,name');

        $projectManagerId = (string) ($timesheet->project?->project_manager_id ?? '');

        if ($projectManagerId === '') {
            return;
        }

        $actorName = $timesheet->user?->name ?: $this->actorName($actorId);

        $this->sendToUserIds(
            userIds: [$projectManagerId],
            notification: new ProjectActivityNotification(
                title: 'Timesheet submitted',
                message: sprintf(
                    '%s submitted %.2f hour(s) on %s for approval.',
                    $actorName,
                    (float) $timesheet->hours,
                    $timesheet->project?->project_code ?? 'the project',
                ),
                url: '/company/projects/timesheets/'.$timesheet->id.'/edit',
                severity: 'medium',
                meta: [
                    'project_id' => $timesheet->project_id,
                    'timesheet_id' => $timesheet->id,
                    'hours' => (float) $timesheet->hours,
                ],
            ),
            severity: 'medium',
            event: 'Project timesheet submitted',
            source: 'projects.timesheets',
            actorId: $actorId,
        );
    }

    public function notifyTimesheetDecision(
        ProjectTimesheet $timesheet,
        string $decision,
        ?string $actorId = null
    ): void {
        $timesheet->loadMissing('project:id,id,project_code', 'user:id,name');

        if (! $timesheet->user_id) {
            return;
        }

        $actorName = $this->actorName($actorId);
        $decisionLabel = strtolower(trim($decision)) === 'rejected'
            ? 'rejected'
            : 'approved';
        $message = $decisionLabel === 'approved'
            ? sprintf(
                '%s approved your timesheet for %s.',
                $actorName,
                $timesheet->project?->project_code ?? 'the project',
            )
            : sprintf(
                '%s rejected your timesheet for %s.',
                $actorName,
                $timesheet->project?->project_code ?? 'the project',
            );

        if ($decisionLabel === 'rejected' && filled($timesheet->rejection_reason)) {
            $message .= ' Reason: '.trim((string) $timesheet->rejection_reason);
        }

        $this->sendToUserIds(
            userIds: [(string) $timesheet->user_id],
            notification: new ProjectActivityNotification(
                title: 'Timesheet '.ucfirst($decisionLabel),
                message: $message,
                url: '/company/projects/timesheets/'.$timesheet->id.'/edit',
                severity: $decisionLabel === 'approved' ? 'medium' : 'high',
                meta: [
                    'project_id' => $timesheet->project_id,
                    'timesheet_id' => $timesheet->id,
                    'decision' => $decisionLabel,
                ],
            ),
            severity: $decisionLabel === 'approved' ? 'medium' : 'high',
            event: 'Project timesheet '.ucfirst($decisionLabel),
            source: 'projects.timesheets',
            actorId: $actorId,
        );
    }

    public function notifyRecurringBillingFailure(
        ProjectRecurringBillingRun $run,
        ?string $actorId = null
    ): void {
        $run->loadMissing('project:id,id,project_code,project_manager_id', 'recurringBilling:id,name');

        $projectManagerId = (string) ($run->project?->project_manager_id ?? '');

        if ($projectManagerId === '') {
            return;
        }

        $this->sendToUserIds(
            userIds: [$projectManagerId],
            notification: new ProjectActivityNotification(
                title: 'Recurring billing failed',
                message: sprintf(
                    'Recurring billing run %s failed for %s. %s',
                    $run->cycle_label,
                    $run->project?->project_code ?? 'the project',
                    trim((string) ($run->error_message ?? '')),
                ),
                url: '/company/projects/'.$run->project_id,
                severity: 'high',
                meta: [
                    'project_id' => $run->project_id,
                    'recurring_billing_id' => $run->project_recurring_billing_id,
                    'run_id' => $run->id,
                    'cycle_label' => $run->cycle_label,
                ],
            ),
            severity: 'high',
            event: 'Recurring billing failed',
            source: 'projects.recurring_billing',
            actorId: $actorId,
        );
    }

    /**
     * @param  array<int, string>  $userIds
     */
    private function sendToUserIds(
        array $userIds,
        ProjectActivityNotification $notification,
        string $severity,
        string $event,
        string $source,
        ?string $actorId = null
    ): void {
        $recipients = User::query()
            ->whereIn('id', array_values(array_filter($userIds)))
            ->get();

        if ($actorId) {
            $recipients = $recipients->reject(fn (User $user) => (string) $user->id === (string) $actorId)->values();
        }

        $this->notificationGovernance->notify(
            recipients: $recipients,
            notification: $notification,
            severity: $severity,
            context: [
                'event' => $event,
                'source' => $source,
            ],
        );
    }

    private function actorName(?string $actorId = null): string
    {
        if (! $actorId) {
            return 'System';
        }

        return (string) (User::query()->whereKey($actorId)->value('name') ?? 'System');
    }
}
