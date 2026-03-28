<?php

namespace App\Modules\Hr;

use App\Modules\Accounting\AccountingManualJournalWorkflowService;
use App\Modules\Accounting\AccountingSetupService;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingJournal;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Hr\Models\HrPayrollRun;
use Illuminate\Validation\ValidationException;

class HrPayrollAccountingService
{
    public function __construct(
        private readonly AccountingSetupService $accountingSetupService,
        private readonly AccountingManualJournalWorkflowService $manualJournalWorkflowService,
    ) {}

    public function postRun(HrPayrollRun $run, ?string $actorId = null): AccountingManualJournal
    {
        $run->loadMissing('payrollPeriod', 'company:id,currency_code');

        if (! $run->payrollPeriod) {
            throw ValidationException::withMessages([
                'payroll_run' => 'Payroll period is required before posting payroll.',
            ]);
        }

        $currencyCode = $run->company?->currency_code ?: 'USD';
        $setup = $this->accountingSetupService->ensureCompanySetup(
            companyId: (string) $run->company_id,
            currencyCode: $currencyCode,
            actorId: $actorId,
        );

        $accounts = $this->ensurePayrollAccounts((string) $run->company_id, $actorId);
        $generalJournal = $setup['journals'][AccountingJournal::SYSTEM_GENERAL] ?? null;

        if (! $generalJournal) {
            throw ValidationException::withMessages([
                'payroll_run' => 'General journal is not configured for payroll posting.',
            ]);
        }

        $expenseAmount = round((float) $run->total_gross + (float) $run->total_reimbursements, 2);
        $deductionsAmount = round((float) $run->total_deductions, 2);
        $netAmount = round((float) $run->total_net, 2);

        if ($expenseAmount <= 0) {
            throw ValidationException::withMessages([
                'payroll_run' => 'Payroll run total must be positive before posting.',
            ]);
        }

        $manualJournal = $this->manualJournalWorkflowService->createDraft([
            'journal_id' => (string) $generalJournal->id,
            'entry_date' => $run->payrollPeriod->payment_date?->toDateString() ?? $run->payrollPeriod->end_date?->toDateString() ?? now()->toDateString(),
            'reference' => $run->run_number,
            'description' => 'Payroll accrual for '.$run->run_number.' ('.$run->payrollPeriod->name.')',
            'lines' => array_values(array_filter([
                [
                    'account_id' => (string) $accounts[AccountingAccount::SYSTEM_PAYROLL_EXPENSE]->id,
                    'description' => 'Payroll expense',
                    'debit' => $expenseAmount,
                    'credit' => null,
                ],
                $deductionsAmount > 0 ? [
                    'account_id' => (string) $accounts[AccountingAccount::SYSTEM_PAYROLL_DEDUCTIONS_PAYABLE]->id,
                    'description' => 'Payroll deductions payable',
                    'debit' => null,
                    'credit' => $deductionsAmount,
                ] : null,
                [
                    'account_id' => (string) $accounts[AccountingAccount::SYSTEM_PAYROLL_PAYABLE]->id,
                    'description' => 'Payroll payable',
                    'debit' => null,
                    'credit' => $netAmount,
                ],
            ])),
        ], (string) $run->company_id, $actorId);

        if ($manualJournal->requires_approval) {
            $manualJournal->update([
                'approval_status' => AccountingManualJournal::APPROVAL_STATUS_APPROVED,
                'approved_by' => $run->approved_by_user_id ?: $actorId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);
        }

        return $this->manualJournalWorkflowService->post($manualJournal->fresh(), $actorId);
    }

    /**
     * @return array<string, AccountingAccount>
     */
    private function ensurePayrollAccounts(string $companyId, ?string $actorId = null): array
    {
        $definitions = [
            AccountingAccount::SYSTEM_PAYROLL_EXPENSE => [
                'code' => '5100',
                'name' => 'Payroll Expense',
                'account_type' => AccountingAccount::TYPE_EXPENSE,
                'category' => AccountingAccount::CATEGORY_EXPENSE,
                'normal_balance' => AccountingAccount::NORMAL_DEBIT,
                'description' => 'Salary, overtime, and reimbursement expense accrued from payroll runs.',
            ],
            AccountingAccount::SYSTEM_PAYROLL_PAYABLE => [
                'code' => '2100',
                'name' => 'Payroll Payable',
                'account_type' => AccountingAccount::TYPE_LIABILITY,
                'category' => AccountingAccount::CATEGORY_PAYABLE,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'description' => 'Outstanding net payroll liability awaiting payment.',
            ],
            AccountingAccount::SYSTEM_PAYROLL_DEDUCTIONS_PAYABLE => [
                'code' => '2110',
                'name' => 'Payroll Deductions Payable',
                'account_type' => AccountingAccount::TYPE_LIABILITY,
                'category' => AccountingAccount::CATEGORY_PAYABLE,
                'normal_balance' => AccountingAccount::NORMAL_CREDIT,
                'description' => 'Withheld payroll deductions awaiting settlement.',
            ],
        ];

        $accounts = [];

        foreach ($definitions as $systemKey => $definition) {
            $accounts[$systemKey] = AccountingAccount::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'system_key' => $systemKey,
                ],
                [
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'account_type' => $definition['account_type'],
                    'category' => $definition['category'],
                    'normal_balance' => $definition['normal_balance'],
                    'is_active' => true,
                    'is_system' => true,
                    'allows_manual_posting' => false,
                    'description' => $definition['description'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ],
            );
        }

        return $accounts;
    }
}
