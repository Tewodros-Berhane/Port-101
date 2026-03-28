<?php

namespace App\Modules\Hr;

use App\Core\Company\Models\CompanyUser;
use App\Models\User;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrSalaryStructure;
use App\Modules\Hr\Models\HrSalaryStructureLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrPayrollService
{
    public function __construct(
        private readonly HrPayrollWorkEntryService $payrollWorkEntryService,
        private readonly HrPayrollAccountingService $payrollAccountingService,
        private readonly HrPayrollNotificationService $payrollNotificationService,
    ) {}

    public function createSalaryStructure(array $attributes, User $actor): HrSalaryStructure
    {
        $companyId = (string) $actor->current_company_id;
        $this->assertUniqueSalaryStructureCode($companyId, (string) $attributes['code']);

        return DB::transaction(function () use ($attributes, $actor, $companyId) {
            $structure = HrSalaryStructure::create([
                'company_id' => $companyId,
                'name' => $attributes['name'],
                'code' => $attributes['code'],
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->replaceSalaryStructureLines($structure, $attributes['lines'] ?? [], $actor->id);

            return $structure->fresh(['lines']);
        });
    }

    public function updateSalaryStructure(HrSalaryStructure $structure, array $attributes, User $actor): HrSalaryStructure
    {
        $companyId = (string) $structure->company_id;
        $this->assertUniqueSalaryStructureCode($companyId, (string) $attributes['code'], (string) $structure->id);

        return DB::transaction(function () use ($structure, $attributes, $actor) {
            $structure = HrSalaryStructure::query()->lockForUpdate()->findOrFail($structure->id);

            $structure->update([
                'name' => $attributes['name'],
                'code' => $attributes['code'],
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'notes' => $attributes['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);

            $this->replaceSalaryStructureLines($structure, $attributes['lines'] ?? [], $actor->id);

            return $structure->fresh(['lines']);
        });
    }

    public function createCompensationAssignment(array $attributes, User $actor): HrCompensationAssignment
    {
        $companyId = (string) $actor->current_company_id;
        $payload = $this->normalizedAssignmentPayload($companyId, $attributes);
        $this->assertAssignmentDoesNotOverlap($companyId, (string) $payload['employee_id'], $payload['effective_from'], $payload['effective_to']);

        return HrCompensationAssignment::create([
            ...$payload,
            'company_id' => $companyId,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updateCompensationAssignment(HrCompensationAssignment $assignment, array $attributes, User $actor): HrCompensationAssignment
    {
        $payload = $this->normalizedAssignmentPayload((string) $assignment->company_id, $attributes);
        $this->assertAssignmentDoesNotOverlap(
            companyId: (string) $assignment->company_id,
            employeeId: (string) $payload['employee_id'],
            effectiveFrom: $payload['effective_from'],
            effectiveTo: $payload['effective_to'],
            ignoreId: (string) $assignment->id,
        );

        $assignment->update([
            ...$payload,
            'updated_by' => $actor->id,
        ]);

        return $assignment->fresh(['employee', 'contract', 'salaryStructure.lines']);
    }

    public function createPayrollPeriod(array $attributes, User $actor): HrPayrollPeriod
    {
        $companyId = (string) $actor->current_company_id;
        $this->assertPeriodIsValid($companyId, $attributes['start_date'], $attributes['end_date']);

        return HrPayrollPeriod::create([
            'company_id' => $companyId,
            'name' => $attributes['name'],
            'pay_frequency' => $attributes['pay_frequency'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'payment_date' => $attributes['payment_date'],
            'status' => $attributes['status'] ?? HrPayrollPeriod::STATUS_OPEN,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updatePayrollPeriod(HrPayrollPeriod $period, array $attributes, User $actor): HrPayrollPeriod
    {
        $this->assertPeriodIsValid((string) $period->company_id, $attributes['start_date'], $attributes['end_date'], (string) $period->id);

        $period->update([
            'name' => $attributes['name'],
            'pay_frequency' => $attributes['pay_frequency'],
            'start_date' => $attributes['start_date'],
            'end_date' => $attributes['end_date'],
            'payment_date' => $attributes['payment_date'],
            'status' => $attributes['status'] ?? $period->status,
            'updated_by' => $actor->id,
        ]);

        return $period->fresh();
    }

    public function createPayrollRun(array $attributes, User $actor): HrPayrollRun
    {
        $companyId = (string) $actor->current_company_id;
        $period = HrPayrollPeriod::query()
            ->where('company_id', $companyId)
            ->findOrFail($attributes['payroll_period_id']);

        if (HrPayrollRun::query()->where('company_id', $companyId)->where('payroll_period_id', $period->id)->exists()) {
            throw ValidationException::withMessages([
                'payroll_period_id' => 'A payroll run already exists for the selected period.',
            ]);
        }

        $approverUserId = $attributes['approver_user_id'] ?? $this->resolveRunApprover($companyId, $actor->id);

        if (! $approverUserId) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'A payroll approver must be configured before preparing a payroll run.',
            ]);
        }

        if ((string) $approverUserId === (string) $actor->id) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'The preparer cannot also be the payroll approver.',
            ]);
        }

        return HrPayrollRun::create([
            'company_id' => $companyId,
            'payroll_period_id' => $period->id,
            'approver_user_id' => $approverUserId,
            'run_number' => $this->nextRunNumber($companyId, $period),
            'status' => HrPayrollRun::STATUS_DRAFT,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function prepareRun(HrPayrollRun $run, User $actor): HrPayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $run = HrPayrollRun::query()
                ->with(['payrollPeriod', 'payslips.lines'])
                ->lockForUpdate()
                ->findOrFail($run->id);

            if (in_array($run->status, [HrPayrollRun::STATUS_POSTED, HrPayrollRun::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'Posted or cancelled payroll runs cannot be prepared again.',
                ]);
            }

            $approverUserId = $run->approver_user_id ?: $this->resolveRunApprover((string) $run->company_id, $actor->id);

            if (! $approverUserId) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'A payroll approver is required before preparing the run.',
                ]);
            }

            if ((string) $approverUserId === (string) $actor->id) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'The payroll preparer cannot also be the approver.',
                ]);
            }

            $existingPayslipIds = $run->payslips->pluck('id')->all();

            if ($existingPayslipIds !== []) {
                HrReimbursementClaim::query()
                    ->where('company_id', $run->company_id)
                    ->whereIn('payslip_id', $existingPayslipIds)
                    ->update([
                        'payslip_id' => null,
                        'updated_by' => $actor->id,
                    ]);
            }

            $run->payslips()->each(function (HrPayslip $payslip): void {
                $payslip->lines()->delete();
            });
            $run->payslips()->delete();
            $run->workEntries()->delete();

            $totals = $this->payrollWorkEntryService->generateForRun($run, $actor->id);

            if ($totals['employee_count'] === 0) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'No payroll-eligible employees were found for the selected period.',
                ]);
            }

            $run->update([
                'approver_user_id' => $approverUserId,
                'status' => HrPayrollRun::STATUS_PREPARED,
                'prepared_by_user_id' => $actor->id,
                'approved_by_user_id' => null,
                'posted_by_user_id' => null,
                'accounting_manual_journal_id' => null,
                'total_gross' => $totals['gross'],
                'total_deductions' => $totals['deductions'],
                'total_reimbursements' => $totals['reimbursements'],
                'total_net' => $totals['net'],
                'prepared_at' => now(),
                'approved_at' => null,
                'posted_at' => null,
                'decision_notes' => null,
                'updated_by' => $actor->id,
            ]);

            $run->payrollPeriod?->update([
                'status' => HrPayrollPeriod::STATUS_PROCESSING,
                'updated_by' => $actor->id,
            ]);

            return $run->fresh(['payrollPeriod', 'approver', 'payslips.employee', 'payslips.lines', 'workEntries.employee']);
        });
    }

    public function approveRun(HrPayrollRun $run, ?string $actorId = null): HrPayrollRun
    {
        return DB::transaction(function () use ($run, $actorId) {
            $run = HrPayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== HrPayrollRun::STATUS_PREPARED) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'Only prepared payroll runs can be approved.',
                ]);
            }

            if ($actorId && (string) $run->prepared_by_user_id === (string) $actorId) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'The preparer cannot approve the same payroll run.',
                ]);
            }

            $run->update([
                'status' => HrPayrollRun::STATUS_APPROVED,
                'approved_by_user_id' => $actorId,
                'approved_at' => now(),
                'decision_notes' => null,
                'updated_by' => $actorId,
            ]);

            $run->payslips()->update([
                'status' => HrPayslip::STATUS_APPROVED,
                'updated_by' => $actorId,
            ]);

            return $run->fresh(['payslips']);
        });
    }

    public function rejectRun(HrPayrollRun $run, ?string $reason = null, ?string $actorId = null): HrPayrollRun
    {
        return DB::transaction(function () use ($run, $reason, $actorId) {
            $run = HrPayrollRun::query()->lockForUpdate()->findOrFail($run->id);

            if (! in_array($run->status, [HrPayrollRun::STATUS_PREPARED, HrPayrollRun::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'Only prepared or approved payroll runs can be rejected.',
                ]);
            }

            $run->update([
                'status' => HrPayrollRun::STATUS_DRAFT,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'decision_notes' => $reason,
                'updated_by' => $actorId,
            ]);

            $run->payslips()->update([
                'status' => HrPayslip::STATUS_DRAFT,
                'updated_by' => $actorId,
            ]);

            return $run->fresh(['payslips']);
        });
    }

    public function postRun(HrPayrollRun $run, User $actor): HrPayrollRun
    {
        return DB::transaction(function () use ($run, $actor) {
            $run = HrPayrollRun::query()
                ->with(['payrollPeriod', 'payslips', 'workEntries'])
                ->lockForUpdate()
                ->findOrFail($run->id);

            if ($run->status !== HrPayrollRun::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'Only approved payroll runs can be posted.',
                ]);
            }

            $manualJournal = $this->payrollAccountingService->postRun($run, $actor->id);

            $run->update([
                'status' => HrPayrollRun::STATUS_POSTED,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'accounting_manual_journal_id' => $manualJournal->id,
                'updated_by' => $actor->id,
            ]);

            $run->payslips()->update([
                'status' => HrPayslip::STATUS_POSTED,
                'issued_at' => now(),
                'published_at' => now(),
                'published_by_user_id' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $leaveIds = $run->workEntries()
                ->where('source_type', HrLeaveRequest::class)
                ->pluck('source_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($leaveIds !== []) {
                HrLeaveRequest::query()
                    ->where('company_id', $run->company_id)
                    ->whereIn('id', $leaveIds)
                    ->update([
                        'payroll_status' => HrLeaveRequest::PAYROLL_STATUS_CONSUMED,
                        'updated_by' => $actor->id,
                    ]);
            }

            $claimIds = $run->workEntries()
                ->where('source_type', HrReimbursementClaim::class)
                ->pluck('source_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($claimIds !== []) {
                HrReimbursementClaim::query()
                    ->where('company_id', $run->company_id)
                    ->whereIn('id', $claimIds)
                    ->update([
                        'status' => HrReimbursementClaim::STATUS_POSTED,
                        'updated_by' => $actor->id,
                    ]);
            }

            $run->payrollPeriod?->update([
                'status' => HrPayrollPeriod::STATUS_CLOSED,
                'updated_by' => $actor->id,
            ]);

            $run = $run->fresh(['payrollPeriod', 'payslips.employee', 'payslips.lines']);
            $this->payrollNotificationService->notifyPayslipsPublished($run, $actor->id);

            return $run;
        });
    }

    private function replaceSalaryStructureLines(HrSalaryStructure $structure, array $lines, ?string $actorId = null): void
    {
        $structure->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            HrSalaryStructureLine::create([
                'company_id' => (string) $structure->company_id,
                'salary_structure_id' => (string) $structure->id,
                'line_type' => $line['line_type'],
                'calculation_type' => $line['calculation_type'],
                'code' => $line['code'],
                'name' => $line['name'],
                'line_order' => $index + 1,
                'amount' => $line['amount'] !== null && $line['amount'] !== '' ? round((float) $line['amount'], 2) : null,
                'percentage_rate' => $line['percentage_rate'] !== null && $line['percentage_rate'] !== '' ? round((float) $line['percentage_rate'], 4) : null,
                'is_active' => (bool) ($line['is_active'] ?? true),
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedAssignmentPayload(string $companyId, array $attributes): array
    {
        $contract = null;

        if (! empty($attributes['contract_id'])) {
            $contract = HrEmployeeContract::query()
                ->where('company_id', $companyId)
                ->findOrFail($attributes['contract_id']);
        }

        $salaryStructureId = $attributes['salary_structure_id'] ?? null;

        if ($salaryStructureId) {
            HrSalaryStructure::query()
                ->where('company_id', $companyId)
                ->findOrFail($salaryStructureId);
        }

        $payload = [
            'employee_id' => $attributes['employee_id'],
            'contract_id' => $contract?->id,
            'salary_structure_id' => $salaryStructureId ?: null,
            'currency_id' => $attributes['currency_id'] ?: ($contract?->currency_id ?: null),
            'effective_from' => $attributes['effective_from'],
            'effective_to' => $attributes['effective_to'] ?: null,
            'pay_frequency' => $attributes['pay_frequency'] ?: ($contract?->pay_frequency ?: ''),
            'salary_basis' => $attributes['salary_basis'] ?: ($contract?->salary_basis ?: ''),
            'base_salary_amount' => $attributes['base_salary_amount'] !== '' && $attributes['base_salary_amount'] !== null
                ? round((float) $attributes['base_salary_amount'], 2)
                : ($contract?->base_salary_amount !== null ? round((float) $contract->base_salary_amount, 2) : null),
            'hourly_rate' => $attributes['hourly_rate'] !== '' && $attributes['hourly_rate'] !== null
                ? round((float) $attributes['hourly_rate'], 2)
                : ($contract?->hourly_rate !== null ? round((float) $contract->hourly_rate, 2) : null),
            'payroll_group' => $attributes['payroll_group'] ?: null,
            'is_active' => (bool) ($attributes['is_active'] ?? true),
            'notes' => $attributes['notes'] ?: null,
        ];

        if ($payload['pay_frequency'] === '' || $payload['salary_basis'] === '') {
            throw ValidationException::withMessages([
                'contract_id' => 'Pay frequency and salary basis are required for payroll compensation.',
            ]);
        }

        if (($payload['base_salary_amount'] ?? null) === null && ($payload['hourly_rate'] ?? null) === null) {
            throw ValidationException::withMessages([
                'base_salary_amount' => 'Either base salary or hourly rate is required for payroll compensation.',
            ]);
        }

        return $payload;
    }

    private function assertUniqueSalaryStructureCode(string $companyId, string $code, ?string $ignoreId = null): void
    {
        $query = HrSalaryStructure::query()
            ->where('company_id', $companyId)
            ->where('code', $code);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Salary structure code must be unique within the company.',
            ]);
        }
    }

    private function assertAssignmentDoesNotOverlap(
        string $companyId,
        string $employeeId,
        string $effectiveFrom,
        ?string $effectiveTo,
        ?string $ignoreId = null,
    ): void {
        $query = HrCompensationAssignment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->where(function ($overlapQuery) use ($effectiveFrom, $effectiveTo): void {
                $overlapQuery
                    ->whereDate('effective_from', '<=', $effectiveTo ?: '9999-12-31')
                    ->where(function ($endQuery) use ($effectiveFrom): void {
                        $endQuery->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $effectiveFrom);
                    });
            });

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'effective_from' => 'Compensation assignments for the employee cannot overlap.',
            ]);
        }
    }

    private function assertPeriodIsValid(string $companyId, string $startDate, string $endDate, ?string $ignoreId = null): void
    {
        if ($endDate < $startDate) {
            throw ValidationException::withMessages([
                'end_date' => 'Payroll period end date must be on or after the start date.',
            ]);
        }

        $query = HrPayrollPeriod::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Payroll periods cannot overlap within the same company.',
            ]);
        }
    }

    private function resolveRunApprover(string $companyId, string $actorId): ?string
    {
        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('user_id', '!=', $actorId)
            ->where(function ($query): void {
                $query->where('is_owner', true)
                    ->orWhereHas('role', fn ($roleQuery) => $roleQuery->whereIn('slug', ['hr_manager', 'payroll_manager']));
            })
            ->orderByDesc('is_owner')
            ->value('user_id');
    }

    private function nextRunNumber(string $companyId, HrPayrollPeriod $period): string
    {
        $prefix = 'PR-'.$period->start_date?->format('Ym');
        $count = HrPayrollRun::query()
            ->where('company_id', $companyId)
            ->where('run_number', 'like', $prefix.'-%')
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }
}
