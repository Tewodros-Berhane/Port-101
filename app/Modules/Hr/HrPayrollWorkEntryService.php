<?php

namespace App\Modules\Hr;

use App\Core\MasterData\Models\Currency;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayrollWorkEntry;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrPayslipLine;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrSalaryStructureLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HrPayrollWorkEntryService
{
    /**
     * @return array{employee_count:int,gross:float,deductions:float,reimbursements:float,net:float}
     */
    public function generateForRun(HrPayrollRun $run, ?string $actorId = null): array
    {
        $run->loadMissing('payrollPeriod', 'company:id,currency_code');

        /** @var HrPayrollPeriod $period */
        $period = $run->payrollPeriod;
        $companyId = (string) $run->company_id;
        $defaultCurrencyId = $this->resolveDefaultCurrencyId($companyId, $run->company?->currency_code);

        $employeeContexts = $this->eligibleEmployeeContexts($companyId, $period);

        $employeeCount = 0;
        $totalGross = 0.0;
        $totalDeductionsAmount = 0.0;
        $totalReimbursements = 0.0;
        $totalNet = 0.0;

        foreach ($employeeContexts as $context) {
            /** @var HrEmployee $employee */
            $employee = $context['employee'];
            /** @var HrEmployeeContract|null $contract */
            $contract = $context['contract'];
            /** @var HrCompensationAssignment|null $assignment */
            $assignment = $context['assignment'];

            $salaryBasis = (string) ($context['salary_basis'] ?? HrEmployeeContract::SALARY_BASIS_FIXED);
            $baseSalary = round((float) ($context['base_salary_amount'] ?? 0), 2);
            $hourlyRate = round((float) ($context['hourly_rate'] ?? 0), 2);
            $standardHoursPerDay = round((float) ($context['standard_hours_per_day'] ?? 8), 2);
            $workingDaysPerWeek = max(1, (int) ($context['working_days_per_week'] ?? 5));
            $currencyId = (string) ($context['currency_id'] ?? $defaultCurrencyId ?? '');

            if ($currencyId === '') {
                continue;
            }

            $hourlyRate = $this->resolveHourlyRate(
                salaryBasis: $salaryBasis,
                hourlyRate: $hourlyRate,
                baseSalary: $baseSalary,
                standardHoursPerDay: $standardHoursPerDay,
                workingDaysPerWeek: $workingDaysPerWeek,
                payFrequency: (string) $context['pay_frequency'],
            );

            $attendanceRecords = HrAttendanceRecord::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$period->start_date?->toDateString(), $period->end_date?->toDateString()])
                ->whereIn('approval_status', [
                    HrAttendanceRecord::APPROVAL_NOT_REQUIRED,
                    HrAttendanceRecord::APPROVAL_APPROVED,
                ])
                ->get();

            $leaveRequests = HrLeaveRequest::query()
                ->with('leaveType:id,unit,is_paid')
                ->where('company_id', $companyId)
                ->where('employee_id', $employee->id)
                ->where('status', HrLeaveRequest::STATUS_APPROVED)
                ->where('payroll_status', HrLeaveRequest::PAYROLL_STATUS_OPEN)
                ->whereDate('from_date', '<=', $period->end_date?->toDateString())
                ->whereDate('to_date', '>=', $period->start_date?->toDateString())
                ->get();

            $reimbursementClaims = HrReimbursementClaim::query()
                ->where('company_id', $companyId)
                ->where('employee_id', $employee->id)
                ->where('status', HrReimbursementClaim::STATUS_FINANCE_APPROVED)
                ->whereNull('accounting_invoice_id')
                ->whereNull('accounting_payment_id')
                ->whereNull('payslip_id')
                ->where(function ($query) use ($period): void {
                    $query->whereNull('finance_approved_at')
                        ->orWhere('finance_approved_at', '<=', $period->end_date?->endOfDay());
                })
                ->orderBy('finance_approved_at')
                ->get();

            $workEntries = [];
            $workedHours = 0.0;
            $overtimeHours = 0.0;
            $paidLeaveHours = 0.0;
            $unpaidLeaveHours = 0.0;
            $reimbursementAmount = 0.0;

            foreach ($attendanceRecords as $record) {
                $workedQuantity = round(((int) $record->worked_minutes) / 60, 2);
                $overtimeQuantity = round(((int) $record->overtime_minutes) / 60, 2);

                if ($workedQuantity > 0) {
                    $workedHours += $workedQuantity;
                    $workEntries[] = [
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'payroll_period_id' => $period->id,
                        'payroll_run_id' => $run->id,
                        'entry_type' => HrPayrollWorkEntry::TYPE_WORKED_TIME,
                        'source_type' => HrAttendanceRecord::class,
                        'source_id' => $record->id,
                        'from_datetime' => $record->check_in_at,
                        'to_datetime' => $record->check_out_at,
                        'quantity' => $workedQuantity,
                        'amount_reference' => $hourlyRate,
                        'status' => HrPayrollWorkEntry::STATUS_CONFIRMED,
                        'conflict_reason' => null,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ];
                }

                if ($overtimeQuantity > 0) {
                    $overtimeHours += $overtimeQuantity;
                    $workEntries[] = [
                        'company_id' => $companyId,
                        'employee_id' => $employee->id,
                        'payroll_period_id' => $period->id,
                        'payroll_run_id' => $run->id,
                        'entry_type' => HrPayrollWorkEntry::TYPE_OVERTIME,
                        'source_type' => HrAttendanceRecord::class,
                        'source_id' => $record->id,
                        'from_datetime' => $record->check_out_at,
                        'to_datetime' => $record->check_out_at,
                        'quantity' => $overtimeQuantity,
                        'amount_reference' => round($hourlyRate * 1.5, 2),
                        'status' => HrPayrollWorkEntry::STATUS_CONFIRMED,
                        'conflict_reason' => null,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ];
                }
            }

            foreach ($leaveRequests as $leaveRequest) {
                $leaveHours = $this->leaveHoursWithinPeriod($leaveRequest, $period, $standardHoursPerDay);

                if ($leaveHours <= 0) {
                    continue;
                }

                $isPaid = (bool) ($leaveRequest->leaveType?->is_paid ?? false);

                if ($isPaid) {
                    $paidLeaveHours += $leaveHours;
                } else {
                    $unpaidLeaveHours += $leaveHours;
                }

                $workEntries[] = [
                    'company_id' => $companyId,
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'payroll_run_id' => $run->id,
                    'entry_type' => $isPaid
                        ? HrPayrollWorkEntry::TYPE_LEAVE_PAID
                        : HrPayrollWorkEntry::TYPE_LEAVE_UNPAID,
                    'source_type' => HrLeaveRequest::class,
                    'source_id' => $leaveRequest->id,
                    'from_datetime' => $leaveRequest->from_date?->startOfDay(),
                    'to_datetime' => $leaveRequest->to_date?->endOfDay(),
                    'quantity' => round($leaveHours, 2),
                    'amount_reference' => $hourlyRate,
                    'status' => HrPayrollWorkEntry::STATUS_CONFIRMED,
                    'conflict_reason' => null,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ];
            }

            foreach ($reimbursementClaims as $claim) {
                $reimbursementAmount += round((float) $claim->total_amount, 2);

                $workEntries[] = [
                    'company_id' => $companyId,
                    'employee_id' => $employee->id,
                    'payroll_period_id' => $period->id,
                    'payroll_run_id' => $run->id,
                    'entry_type' => HrPayrollWorkEntry::TYPE_REIMBURSEMENT,
                    'source_type' => HrReimbursementClaim::class,
                    'source_id' => $claim->id,
                    'from_datetime' => $claim->finance_approved_at,
                    'to_datetime' => $claim->finance_approved_at,
                    'quantity' => 1,
                    'amount_reference' => round((float) $claim->total_amount, 2),
                    'status' => HrPayrollWorkEntry::STATUS_CONFIRMED,
                    'conflict_reason' => null,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ];
            }

            foreach ($workEntries as $entry) {
                HrPayrollWorkEntry::create($entry);
            }

            $payslipLines = [];
            $grossPay = 0.0;
            $payslipDeductions = 0.0;
            $lineOrder = 1;

            if ($salaryBasis === HrEmployeeContract::SALARY_BASIS_FIXED && $baseSalary > 0) {
                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_EARNING,
                    code: 'BASIC',
                    name: 'Basic salary',
                    lineOrder: $lineOrder++,
                    quantity: 1,
                    rate: $baseSalary,
                    amount: $baseSalary,
                    actorId: $actorId,
                );
                $grossPay += $baseSalary;
            }

            if ($salaryBasis === HrEmployeeContract::SALARY_BASIS_HOURLY && $workedHours > 0) {
                $workedAmount = round($workedHours * $hourlyRate, 2);
                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_EARNING,
                    code: 'WORKED',
                    name: 'Worked time',
                    lineOrder: $lineOrder++,
                    quantity: $workedHours,
                    rate: $hourlyRate,
                    amount: $workedAmount,
                    actorId: $actorId,
                );
                $grossPay += $workedAmount;
            }

            if ($salaryBasis === HrEmployeeContract::SALARY_BASIS_HOURLY && $paidLeaveHours > 0) {
                $paidLeaveAmount = round($paidLeaveHours * $hourlyRate, 2);
                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_EARNING,
                    code: 'PAID_LEAVE',
                    name: 'Paid leave',
                    lineOrder: $lineOrder++,
                    quantity: $paidLeaveHours,
                    rate: $hourlyRate,
                    amount: $paidLeaveAmount,
                    actorId: $actorId,
                );
                $grossPay += $paidLeaveAmount;
            }

            if ($overtimeHours > 0) {
                $overtimeRate = round($hourlyRate * 1.5, 2);
                $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_EARNING,
                    code: 'OVERTIME',
                    name: 'Overtime',
                    lineOrder: $lineOrder++,
                    quantity: $overtimeHours,
                    rate: $overtimeRate,
                    amount: $overtimeAmount,
                    actorId: $actorId,
                );
                $grossPay += $overtimeAmount;
            }

            if ($salaryBasis === HrEmployeeContract::SALARY_BASIS_FIXED && $unpaidLeaveHours > 0) {
                $unpaidLeaveAmount = round($unpaidLeaveHours * $hourlyRate, 2);
                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_DEDUCTION,
                    code: 'UNPAID_LEAVE',
                    name: 'Unpaid leave deduction',
                    lineOrder: $lineOrder++,
                    quantity: $unpaidLeaveHours,
                    rate: $hourlyRate,
                    amount: $unpaidLeaveAmount,
                    actorId: $actorId,
                );
                $payslipDeductions += $unpaidLeaveAmount;
            }

            $salaryStructure = $assignment?->salaryStructure?->loadMissing('lines')?->lines
                ?? collect();
            $baseReferenceAmount = $salaryBasis === HrEmployeeContract::SALARY_BASIS_FIXED
                ? $baseSalary
                : round($grossPay, 2);

            foreach ($salaryStructure as $structureLine) {
                if (! $structureLine instanceof HrSalaryStructureLine || ! $structureLine->is_active) {
                    continue;
                }

                $amount = $structureLine->calculation_type === HrSalaryStructureLine::CALCULATION_PERCENTAGE
                    ? round($baseReferenceAmount * ((float) $structureLine->percentage_rate / 100), 2)
                    : round((float) ($structureLine->amount ?? 0), 2);

                if ($amount <= 0) {
                    continue;
                }

                $lineType = $structureLine->line_type === HrSalaryStructureLine::TYPE_DEDUCTION
                    ? HrPayslipLine::TYPE_DEDUCTION
                    : HrPayslipLine::TYPE_EARNING;

                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: $lineType,
                    code: (string) $structureLine->code,
                    name: (string) $structureLine->name,
                    lineOrder: $lineOrder++,
                    quantity: 1,
                    rate: $amount,
                    amount: $amount,
                    actorId: $actorId,
                    sourceType: HrSalaryStructureLine::class,
                    sourceId: (string) $structureLine->id,
                );

                if ($lineType === HrPayslipLine::TYPE_DEDUCTION) {
                    $payslipDeductions += $amount;
                } else {
                    $grossPay += $amount;
                }
            }

            foreach ($reimbursementClaims as $claim) {
                $claimAmount = round((float) $claim->total_amount, 2);

                if ($claimAmount <= 0) {
                    continue;
                }

                $payslipLines[] = $this->payslipLinePayload(
                    companyId: $companyId,
                    lineType: HrPayslipLine::TYPE_REIMBURSEMENT,
                    code: 'REIMBURSEMENT',
                    name: 'Reimbursement '.$claim->claim_number,
                    lineOrder: $lineOrder++,
                    quantity: 1,
                    rate: $claimAmount,
                    amount: $claimAmount,
                    actorId: $actorId,
                    sourceType: HrReimbursementClaim::class,
                    sourceId: (string) $claim->id,
                );
            }

            if ($grossPay <= 0 && $reimbursementAmount <= 0 && $payslipDeductions <= 0) {
                continue;
            }

            $employeeCount++;
            $netPay = round($grossPay - $payslipDeductions + $reimbursementAmount, 2);
            $payslip = HrPayslip::create([
                'company_id' => $companyId,
                'employee_id' => $employee->id,
                'payroll_run_id' => $run->id,
                'payroll_period_id' => $period->id,
                'compensation_assignment_id' => $assignment?->id,
                'currency_id' => $currencyId,
                'payslip_number' => $this->nextPayslipNumber($run, $employee),
                'status' => HrPayslip::STATUS_DRAFT,
                'gross_pay' => round($grossPay, 2),
                'total_deductions' => round($payslipDeductions, 2),
                'reimbursement_amount' => round($reimbursementAmount, 2),
                'net_pay' => $netPay,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            foreach ($payslipLines as $line) {
                HrPayslipLine::create([
                    ...$line,
                    'payslip_id' => $payslip->id,
                ]);
            }

            foreach ($reimbursementClaims as $claim) {
                $claim->update([
                    'payslip_id' => $payslip->id,
                    'updated_by' => $actorId,
                ]);
            }

            $totalGross += round($grossPay, 2);
            $totalDeductionsAmount += round($payslipDeductions, 2);
            $totalReimbursements += round($reimbursementAmount, 2);
            $totalNet += $netPay;
        }

        return [
            'employee_count' => $employeeCount,
            'gross' => round($totalGross, 2),
            'deductions' => round($totalDeductionsAmount, 2),
            'reimbursements' => round($totalReimbursements, 2),
            'net' => round($totalNet, 2),
        ];
    }

    /**
     * @return Collection<int, array{employee:HrEmployee,assignment:?HrCompensationAssignment,contract:?HrEmployeeContract,pay_frequency:string,salary_basis:string,base_salary_amount:float,hourly_rate:float,currency_id:?string,standard_hours_per_day:float,working_days_per_week:int}>
     */
    private function eligibleEmployeeContexts(string $companyId, HrPayrollPeriod $period): Collection
    {
        return HrEmployee::query()
            ->where('company_id', $companyId)
            ->whereIn('employment_status', [
                HrEmployee::STATUS_ACTIVE,
                HrEmployee::STATUS_LEAVE,
            ])
            ->with([
                'contracts' => fn ($query) => $query
                    ->where('status', HrEmployeeContract::STATUS_ACTIVE)
                    ->where('is_payroll_eligible', true)
                    ->whereDate('start_date', '<=', $period->end_date?->toDateString())
                    ->where(function ($dateQuery) use ($period): void {
                        $dateQuery->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $period->start_date?->toDateString());
                    })
                    ->orderByDesc('start_date'),
                'compensationAssignments' => fn ($query) => $query
                    ->with('salaryStructure.lines')
                    ->where('is_active', true)
                    ->whereDate('effective_from', '<=', $period->end_date?->toDateString())
                    ->where(function ($dateQuery) use ($period): void {
                        $dateQuery->whereNull('effective_to')
                            ->orWhereDate('effective_to', '>=', $period->start_date?->toDateString());
                    })
                    ->orderByDesc('effective_from'),
            ])
            ->get()
            ->map(function (HrEmployee $employee) use ($period): ?array {
                $assignment = $employee->compensationAssignments
                    ->first(fn (HrCompensationAssignment $assignment) => $assignment->pay_frequency === $period->pay_frequency)
                    ?: $employee->compensationAssignments->first();

                $contract = $employee->contracts
                    ->first(fn (HrEmployeeContract $contract) => $contract->pay_frequency === $period->pay_frequency)
                    ?: $employee->contracts->first();

                if (! $assignment && ! $contract) {
                    return null;
                }

                $payFrequency = (string) ($assignment?->pay_frequency ?: $contract?->pay_frequency ?: '');

                if ($payFrequency === '' || $payFrequency !== $period->pay_frequency) {
                    return null;
                }

                return [
                    'employee' => $employee,
                    'assignment' => $assignment,
                    'contract' => $contract,
                    'pay_frequency' => $payFrequency,
                    'salary_basis' => (string) ($assignment?->salary_basis ?: $contract?->salary_basis ?: HrEmployeeContract::SALARY_BASIS_FIXED),
                    'base_salary_amount' => (float) ($assignment?->base_salary_amount ?? $contract?->base_salary_amount ?? 0),
                    'hourly_rate' => (float) ($assignment?->hourly_rate ?? $contract?->hourly_rate ?? 0),
                    'currency_id' => $assignment?->currency_id ?: $contract?->currency_id,
                    'standard_hours_per_day' => (float) ($contract?->standard_hours_per_day ?? 8),
                    'working_days_per_week' => (int) ($contract?->working_days_per_week ?? 5),
                ];
            })
            ->filter()
            ->values();
    }

    private function resolveHourlyRate(
        string $salaryBasis,
        float $hourlyRate,
        float $baseSalary,
        float $standardHoursPerDay,
        int $workingDaysPerWeek,
        string $payFrequency,
    ): float {
        if ($hourlyRate > 0) {
            return round($hourlyRate, 2);
        }

        if ($salaryBasis !== HrEmployeeContract::SALARY_BASIS_FIXED || $baseSalary <= 0) {
            return 0.0;
        }

        $weeks = match ($payFrequency) {
            HrEmployeeContract::PAY_FREQUENCY_WEEKLY => 1,
            HrEmployeeContract::PAY_FREQUENCY_BIWEEKLY => 2,
            default => 4.3333,
        };

        $periodHours = max(1, round($workingDaysPerWeek * $standardHoursPerDay * $weeks, 2));

        return round($baseSalary / $periodHours, 2);
    }

    private function leaveHoursWithinPeriod(HrLeaveRequest $leaveRequest, HrPayrollPeriod $period, float $standardHoursPerDay): float
    {
        $leaveType = $leaveRequest->leaveType;

        if (! $leaveType) {
            return 0.0;
        }

        if ($leaveType->unit === 'hours') {
            return round((float) $leaveRequest->duration_amount, 2);
        }

        $overlapStart = Carbon::parse(max(
            (string) $leaveRequest->from_date?->toDateString(),
            (string) $period->start_date?->toDateString(),
        ));
        $overlapEnd = Carbon::parse(min(
            (string) $leaveRequest->to_date?->toDateString(),
            (string) $period->end_date?->toDateString(),
        ));

        if ($overlapEnd->lt($overlapStart)) {
            return 0.0;
        }

        $days = (float) $overlapStart->diffInDays($overlapEnd) + 1;

        if ((bool) $leaveRequest->is_half_day && $days === 1.0) {
            $days = 0.5;
        }

        return round($days * $standardHoursPerDay, 2);
    }

    private function resolveDefaultCurrencyId(string $companyId, ?string $companyCurrencyCode): ?string
    {
        $companyCurrency = $companyCurrencyCode
            ? Currency::query()
                ->where('company_id', $companyId)
                ->where('code', $companyCurrencyCode)
                ->value('id')
            : null;

        if ($companyCurrency) {
            return (string) $companyCurrency;
        }

        $firstActive = Currency::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->value('id');

        return $firstActive ? (string) $firstActive : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function payslipLinePayload(
        string $companyId,
        string $lineType,
        string $code,
        string $name,
        int $lineOrder,
        float $quantity,
        float $rate,
        float $amount,
        ?string $actorId = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
    ): array {
        return [
            'company_id' => $companyId,
            'line_type' => $lineType,
            'code' => $code,
            'name' => $name,
            'line_order' => $lineOrder,
            'quantity' => round($quantity, 2),
            'rate' => round($rate, 2),
            'amount' => round($amount, 2),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    private function nextPayslipNumber(HrPayrollRun $run, HrEmployee $employee): string
    {
        return sprintf('PSL-%s-%s', $run->run_number, $employee->employee_number ?: substr((string) $employee->id, 0, 8));
    }
}
