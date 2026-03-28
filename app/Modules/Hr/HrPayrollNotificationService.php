<?php

namespace App\Modules\Hr;

use App\Core\Notifications\NotificationGovernanceService;
use App\Models\User;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Notifications\HrActivityNotification;

class HrPayrollNotificationService
{
    public function __construct(
        private readonly NotificationGovernanceService $notificationGovernance,
    ) {}

    public function notifyPayslipsPublished(HrPayrollRun $run, ?string $actorId = null): void
    {
        $run->loadMissing('payrollPeriod', 'payslips.employee:id,user_id,display_name');

        foreach ($run->payslips as $payslip) {
            $recipientId = (string) ($payslip->employee?->user_id ?? '');

            if ($recipientId === '' || (string) $recipientId === (string) $actorId) {
                continue;
            }

            $recipients = User::query()->whereKey($recipientId)->get();

            if ($recipients->isEmpty()) {
                continue;
            }

            $this->notificationGovernance->notify(
                recipients: $recipients,
                notification: new HrActivityNotification(
                    title: 'Payslip ready',
                    message: sprintf(
                        '%s published payslip %s for %s.',
                        $this->actorName($actorId),
                        $payslip->payslip_number,
                        $run->payrollPeriod?->name ?? 'the latest payroll period',
                    ),
                    url: '/company/hr/payroll/payslips/'.$payslip->id,
                    severity: 'medium',
                    meta: [
                        'payslip_id' => $payslip->id,
                        'payslip_number' => $payslip->payslip_number,
                        'employee_id' => $payslip->employee_id,
                        'payroll_run_id' => $run->id,
                    ],
                ),
                severity: 'medium',
                context: [
                    'event' => 'Payslip published',
                    'source' => 'hr.payroll',
                ],
            );
        }
    }

    private function actorName(?string $actorId = null): string
    {
        if (! $actorId) {
            return 'System';
        }

        return (string) (User::query()->whereKey($actorId)->value('name') ?? 'System');
    }
}
