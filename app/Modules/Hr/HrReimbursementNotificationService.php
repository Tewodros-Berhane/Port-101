<?php

namespace App\Modules\Hr;

use App\Core\Notifications\NotificationGovernanceService;
use App\Models\User;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Notifications\HrActivityNotification;

class HrReimbursementNotificationService
{
    public function __construct(
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    public function notifySubmitted(HrReimbursementClaim $claim, ?string $actorId = null): void
    {
        $claim->loadMissing('employee:id,display_name', 'approver:id');

        if (! $claim->approver_user_id) {
            return;
        }

        $this->sendToUserIds(
            userIds: [(string) $claim->approver_user_id],
            notification: new HrActivityNotification(
                title: 'Reimbursement submitted',
                message: sprintf(
                    '%s submitted reimbursement claim %s for %s.',
                    $this->actorName($actorId),
                    $claim->claim_number,
                    $claim->employee?->display_name ?? 'an employee',
                ),
                url: '/company/hr/reimbursements',
                severity: 'medium',
                meta: [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'employee_id' => $claim->employee_id,
                ],
            ),
            severity: 'medium',
            event: 'Reimbursement submitted',
            source: 'hr.reimbursements',
            actorId: $actorId,
        );
    }

    public function notifyDecision(HrReimbursementClaim $claim, string $decision, ?string $actorId = null): void
    {
        $claim->loadMissing('employee:id,display_name,user_id');

        $recipientId = (string) ($claim->employee?->user_id ?? '');

        if ($recipientId === '') {
            return;
        }

        $decisionLabel = strtolower(trim($decision)) === 'rejected'
            ? 'rejected'
            : 'approved';
        $message = sprintf(
            '%s %s reimbursement claim %s.',
            $this->actorName($actorId),
            $decisionLabel,
            $claim->claim_number,
        );

        $this->sendToUserIds(
            userIds: [$recipientId],
            notification: new HrActivityNotification(
                title: 'Reimbursement '.ucfirst($decisionLabel),
                message: $message,
                url: '/company/hr/reimbursements',
                severity: $decisionLabel === 'approved' ? 'medium' : 'high',
                meta: [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'employee_id' => $claim->employee_id,
                    'decision' => $decisionLabel,
                ],
            ),
            severity: $decisionLabel === 'approved' ? 'medium' : 'high',
            event: 'Reimbursement '.ucfirst($decisionLabel),
            source: 'hr.reimbursements',
            actorId: $actorId,
        );
    }

    public function notifyPaid(HrReimbursementClaim $claim, ?string $actorId = null): void
    {
        $claim->loadMissing('employee:id,display_name,user_id');

        $recipientId = (string) ($claim->employee?->user_id ?? '');

        if ($recipientId === '') {
            return;
        }

        $this->sendToUserIds(
            userIds: [$recipientId],
            notification: new HrActivityNotification(
                title: 'Reimbursement paid',
                message: sprintf(
                    '%s marked reimbursement claim %s as paid.',
                    $this->actorName($actorId),
                    $claim->claim_number,
                ),
                url: '/company/hr/reimbursements',
                severity: 'medium',
                meta: [
                    'claim_id' => $claim->id,
                    'claim_number' => $claim->claim_number,
                    'employee_id' => $claim->employee_id,
                ],
            ),
            severity: 'medium',
            event: 'Reimbursement paid',
            source: 'hr.reimbursements',
            actorId: $actorId,
        );
    }

    /**
     * @param  array<int, string>  $userIds
     */
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
            $recipients = $recipients
                ->reject(fn (User $user) => (string) $user->id === (string) $actorId)
                ->values();
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
