<?php

namespace App\Modules\Hr;

use App\Core\Notifications\NotificationGovernanceService;
use App\Models\User;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Notifications\HrActivityNotification;

class HrLeaveNotificationService
{
    public function __construct(
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    public function notifyLeaveSubmitted(HrLeaveRequest $leaveRequest, ?string $actorId = null): void
    {
        $leaveRequest->loadMissing('employee:id,display_name', 'leaveType:id,name', 'approver:id');

        if (! $leaveRequest->approver_user_id) {
            return;
        }

        $actorName = $this->actorName($actorId);

        $this->sendToUserIds(
            userIds: [(string) $leaveRequest->approver_user_id],
            notification: new HrActivityNotification(
                title: 'Leave request submitted',
                message: sprintf(
                    '%s submitted %s leave for %s.',
                    $actorName,
                    $leaveRequest->leaveType?->name ?? 'a',
                    $leaveRequest->employee?->display_name ?? 'an employee',
                ),
                url: '/company/hr/leave',
                severity: 'medium',
                meta: [
                    'leave_request_id' => $leaveRequest->id,
                    'request_number' => $leaveRequest->request_number,
                    'employee_id' => $leaveRequest->employee_id,
                ],
            ),
            severity: 'medium',
            event: 'Leave request submitted',
            source: 'hr.leave',
            actorId: $actorId,
        );
    }

    public function notifyLeaveDecision(HrLeaveRequest $leaveRequest, string $decision, ?string $actorId = null): void
    {
        $leaveRequest->loadMissing('employee:id,display_name,user_id', 'leaveType:id,name');

        $recipientId = (string) ($leaveRequest->employee?->user_id ?? '');

        if ($recipientId === '') {
            return;
        }

        $decisionLabel = strtolower(trim($decision)) === 'rejected' ? 'rejected' : 'approved';
        $actorName = $this->actorName($actorId);
        $message = sprintf(
            '%s %s leave request %s.',
            $actorName,
            $decisionLabel,
            $leaveRequest->request_number,
        );

        if ($decisionLabel === 'rejected' && filled($leaveRequest->decision_notes)) {
            $message .= ' Reason: '.trim((string) $leaveRequest->decision_notes);
        }

        $this->sendToUserIds(
            userIds: [$recipientId],
            notification: new HrActivityNotification(
                title: 'Leave request '.ucfirst($decisionLabel),
                message: $message,
                url: '/company/hr/leave',
                severity: $decisionLabel === 'approved' ? 'medium' : 'high',
                meta: [
                    'leave_request_id' => $leaveRequest->id,
                    'request_number' => $leaveRequest->request_number,
                    'decision' => $decisionLabel,
                ],
            ),
            severity: $decisionLabel === 'approved' ? 'medium' : 'high',
            event: 'Leave request '.ucfirst($decisionLabel),
            source: 'hr.leave',
            actorId: $actorId,
        );
    }

    private function sendToUserIds(
        array $userIds,
        HrActivityNotification $notification,
        string $severity,
        string $event,
        string $source,
        ?string $actorId = null,
    ): void {
        $recipients = User::query()
            ->whereIn('id', array_values(array_filter($userIds)))
            ->get();

        if ($actorId) {
            $recipients = $recipients->reject(fn (User $user) => (string) $user->id === (string) $actorId)->values();
        }

        if ($recipients->isEmpty()) {
            return;
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
