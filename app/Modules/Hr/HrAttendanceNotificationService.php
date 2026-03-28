<?php

namespace App\Modules\Hr;

use App\Core\Notifications\NotificationGovernanceService;
use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Notifications\HrActivityNotification;

class HrAttendanceNotificationService
{
    public function __construct(
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    public function notifyCorrectionSubmitted(HrAttendanceRequest $attendanceRequest, ?string $actorId = null): void
    {
        $attendanceRequest->loadMissing('employee:id,display_name', 'approver:id');

        if (! $attendanceRequest->approver_user_id) {
            return;
        }

        $actorName = $this->actorName($actorId);

        $this->sendToUserIds(
            userIds: [(string) $attendanceRequest->approver_user_id],
            notification: new HrActivityNotification(
                title: 'Attendance correction submitted',
                message: sprintf(
                    '%s submitted attendance correction %s for %s.',
                    $actorName,
                    $attendanceRequest->request_number,
                    $attendanceRequest->employee?->display_name ?? 'an employee',
                ),
                url: '/company/hr/attendance',
                severity: 'medium',
                meta: [
                    'attendance_request_id' => $attendanceRequest->id,
                    'request_number' => $attendanceRequest->request_number,
                    'employee_id' => $attendanceRequest->employee_id,
                ],
            ),
            severity: 'medium',
            event: 'Attendance correction submitted',
            source: 'hr.attendance',
            actorId: $actorId,
        );
    }

    public function notifyCorrectionDecision(HrAttendanceRequest $attendanceRequest, string $decision, ?string $actorId = null): void
    {
        $attendanceRequest->loadMissing('employee:id,display_name,user_id');

        $recipientId = (string) ($attendanceRequest->employee?->user_id ?? '');

        if ($recipientId === '') {
            return;
        }

        $decisionLabel = strtolower(trim($decision)) === 'rejected' ? 'rejected' : 'approved';
        $actorName = $this->actorName($actorId);
        $message = sprintf(
            '%s %s attendance correction %s.',
            $actorName,
            $decisionLabel,
            $attendanceRequest->request_number,
        );

        if ($decisionLabel === 'rejected' && filled($attendanceRequest->decision_notes)) {
            $message .= ' Reason: '.trim((string) $attendanceRequest->decision_notes);
        }

        $this->sendToUserIds(
            userIds: [$recipientId],
            notification: new HrActivityNotification(
                title: 'Attendance correction '.ucfirst($decisionLabel),
                message: $message,
                url: '/company/hr/attendance',
                severity: $decisionLabel === 'approved' ? 'medium' : 'high',
                meta: [
                    'attendance_request_id' => $attendanceRequest->id,
                    'request_number' => $attendanceRequest->request_number,
                    'employee_id' => $attendanceRequest->employee_id,
                ],
            ),
            severity: $decisionLabel === 'approved' ? 'medium' : 'high',
            event: 'Attendance correction '.ucfirst($decisionLabel),
            source: 'hr.attendance',
            actorId: $actorId,
        );
    }

    /**
     * @param  array<int, string>  $userIds
     */
    private function sendToUserIds(array $userIds, HrActivityNotification $notification, string $severity, string $event, string $source, ?string $actorId = null): void
    {
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

    private function actorName(?string $actorId): string
    {
        if (! $actorId) {
            return 'A team member';
        }

        return User::query()->whereKey($actorId)->value('name')
            ?? 'A team member';
    }
}
