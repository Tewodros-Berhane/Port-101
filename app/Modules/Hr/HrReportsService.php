<?php

namespace App\Modules\Hr;

use App\Core\Company\Models\Company;
use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Carbon\CarbonImmutable;

class HrReportsService
{
    public const REPORT_EMPLOYEE_DIRECTORY = 'hr-employee-directory';

    public const REPORT_HEADCOUNT_SNAPSHOT = 'hr-headcount-snapshot';

    public const REPORT_LEAVE_BALANCES = 'hr-leave-balances';

    public const REPORT_ATTENDANCE_ANOMALIES = 'hr-attendance-anomalies';

    public const REPORT_REIMBURSEMENT_AGING = 'hr-reimbursement-aging';

    public const REPORT_PAYROLL_REGISTER = 'hr-payroll-register';

    public const REPORT_PAYSLIP_SUMMARY = 'hr-payslip-summary';

    /**
     * @var array<int, string>
     */
    public const REPORT_KEYS = [
        self::REPORT_EMPLOYEE_DIRECTORY,
        self::REPORT_HEADCOUNT_SNAPSHOT,
        self::REPORT_LEAVE_BALANCES,
        self::REPORT_ATTENDANCE_ANOMALIES,
        self::REPORT_REIMBURSEMENT_AGING,
        self::REPORT_PAYROLL_REGISTER,
        self::REPORT_PAYSLIP_SUMMARY,
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{key: string, title: string, description: string, row_count: int}>
     */
    public function reportCatalog(Company $company, User $viewer, array $filters): array
    {
        return [
            [
                'key' => self::REPORT_EMPLOYEE_DIRECTORY,
                'title' => 'Employee directory',
                'description' => 'Employee master records available to the current HR viewer.',
                'row_count' => count($this->employeeDirectoryRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_HEADCOUNT_SNAPSHOT,
                'title' => 'Headcount snapshot',
                'description' => 'Headcount, joiners, leavers, and staffing distribution across the selected window.',
                'row_count' => count($this->headcountSnapshotRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_LEAVE_BALANCES,
                'title' => 'Leave balances',
                'description' => 'Allocated, used, and remaining leave by employee, type, and period.',
                'row_count' => count($this->leaveBalanceRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_ATTENDANCE_ANOMALIES,
                'title' => 'Attendance anomalies',
                'description' => 'Missing punches, absences, late arrivals, and overtime exceptions.',
                'row_count' => count($this->attendanceAnomalyRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_REIMBURSEMENT_AGING,
                'title' => 'Reimbursement aging',
                'description' => 'Outstanding reimbursement claims and their aging across manager, finance, and payout stages.',
                'row_count' => count($this->reimbursementAgingRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_PAYROLL_REGISTER,
                'title' => 'Payroll register',
                'description' => 'Employee payslips by payroll period with gross, deductions, reimbursements, and net pay.',
                'row_count' => count($this->payrollRegisterRows($company, $viewer, $filters)),
            ],
            [
                'key' => self::REPORT_PAYSLIP_SUMMARY,
                'title' => 'Payslip summary',
                'description' => 'Published payslip totals, run counts, and payroll issuance signals for the selected window.',
                'row_count' => count($this->payslipSummaryRows($company, $viewer, $filters)),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{key: string, title: string, subtitle: string, columns: array<int, string>, rows: array<int, array<int, string|int|float>>}|null
     */
    public function buildReport(
        Company $company,
        User $viewer,
        string $reportKey,
        array $filters,
    ): ?array {
        return match ($reportKey) {
            self::REPORT_EMPLOYEE_DIRECTORY => [
                'key' => self::REPORT_EMPLOYEE_DIRECTORY,
                'title' => 'Employee Directory Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Employee #', 'Name', 'Status', 'Type', 'Department', 'Designation', 'Hire date', 'Linked user'],
                'rows' => $this->employeeDirectoryRows($company, $viewer, $filters),
            ],
            self::REPORT_HEADCOUNT_SNAPSHOT => [
                'key' => self::REPORT_HEADCOUNT_SNAPSHOT,
                'title' => 'Headcount Snapshot Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->headcountSnapshotRows($company, $viewer, $filters),
            ],
            self::REPORT_LEAVE_BALANCES => [
                'key' => self::REPORT_LEAVE_BALANCES,
                'title' => 'Leave Balances Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Employee #', 'Employee', 'Leave type', 'Period', 'Allocated', 'Used', 'Balance', 'Expires'],
                'rows' => $this->leaveBalanceRows($company, $viewer, $filters),
            ],
            self::REPORT_ATTENDANCE_ANOMALIES => [
                'key' => self::REPORT_ATTENDANCE_ANOMALIES,
                'title' => 'Attendance Anomalies Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Date', 'Employee #', 'Employee', 'Status', 'Worked min', 'Late min', 'Overtime min'],
                'rows' => $this->attendanceAnomalyRows($company, $viewer, $filters),
            ],
            self::REPORT_REIMBURSEMENT_AGING => [
                'key' => self::REPORT_REIMBURSEMENT_AGING,
                'title' => 'Reimbursement Aging Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Claim #', 'Employee', 'Status', 'Currency', 'Total', 'Submitted at', 'Days open'],
                'rows' => $this->reimbursementAgingRows($company, $viewer, $filters),
            ],
            self::REPORT_PAYROLL_REGISTER => [
                'key' => self::REPORT_PAYROLL_REGISTER,
                'title' => 'Payroll Register Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Payslip #', 'Employee #', 'Employee', 'Period', 'Status', 'Gross', 'Deductions', 'Reimbursements', 'Net', 'Issued at'],
                'rows' => $this->payrollRegisterRows($company, $viewer, $filters),
            ],
            self::REPORT_PAYSLIP_SUMMARY => [
                'key' => self::REPORT_PAYSLIP_SUMMARY,
                'title' => 'Payslip Summary Report',
                'subtitle' => $this->subtitle($filters),
                'columns' => ['Metric', 'Value'],
                'rows' => $this->payslipSummaryRows($company, $viewer, $filters),
            ],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dateRange(array $filters): array
    {
        $trendWindow = (int) ($filters['trend_window'] ?? 30);

        if (! in_array($trendWindow, [7, 30, 90], true)) {
            $trendWindow = 30;
        }

        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($trendWindow - 1)->startOfDay();

        if (! empty($filters['start_date'])) {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['start_date'])->startOfDay();
            } catch (\Throwable) {
            }
        }

        if (! empty($filters['end_date'])) {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d', (string) $filters['end_date'])->endOfDay();
            } catch (\Throwable) {
            }
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function employeeDirectoryRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        return HrEmployee::query()
            ->with(['department:id,name', 'designation:id,name', 'user:id,name'])
            ->where('company_id', $company->id)
            ->accessibleTo($viewer)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('hire_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereNull('hire_date')
                    ->orWhere('hire_date', '<=', $end->toDateString());
            })
            ->orderBy('display_name')
            ->get()
            ->map(fn (HrEmployee $employee) => [
                (string) $employee->employee_number,
                (string) $employee->display_name,
                str_replace('_', ' ', (string) $employee->employment_status),
                str_replace('_', ' ', (string) $employee->employment_type),
                (string) ($employee->department?->name ?? '-'),
                (string) ($employee->designation?->name ?? '-'),
                (string) ($employee->hire_date?->toDateString() ?? '-'),
                (string) ($employee->user?->name ?? '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function headcountSnapshotRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $employees = HrEmployee::query()
            ->where('company_id', $company->id)
            ->accessibleTo($viewer);

        return [
            ['Employees total', (clone $employees)->count()],
            ['Active employees', (clone $employees)->where('employment_status', HrEmployee::STATUS_ACTIVE)->count()],
            ['On leave employees', (clone $employees)->where('employment_status', HrEmployee::STATUS_LEAVE)->count()],
            ['Inactive employees', (clone $employees)->where('employment_status', HrEmployee::STATUS_INACTIVE)->count()],
            ['Offboarded employees', (clone $employees)->where('employment_status', HrEmployee::STATUS_OFFBOARDED)->count()],
            ['Joiners in window', (clone $employees)->whereBetween('hire_date', [$start->toDateString(), $end->toDateString()])->count()],
            ['Leavers in window', (clone $employees)->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])->count()],
            ['Departments represented', (clone $employees)->whereNotNull('department_id')->distinct('department_id')->count('department_id')],
            ['Linked user accounts', (clone $employees)->whereNotNull('user_id')->count()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function leaveBalanceRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        return HrLeaveAllocation::query()
            ->with(['employee:id,display_name,employee_number,user_id', 'leaveType:id,name', 'leavePeriod:id,name,start_date,end_date'])
            ->where('company_id', $company->id)
            ->whereHas('employee', fn ($query) => $query->accessibleTo($viewer))
            ->whereHas('leavePeriod', function ($query) use ($start, $end): void {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (HrLeaveAllocation $allocation) => [
                (string) ($allocation->employee?->employee_number ?? '-'),
                (string) ($allocation->employee?->display_name ?? '-'),
                (string) ($allocation->leaveType?->name ?? '-'),
                (string) ($allocation->leavePeriod?->name ?? '-'),
                (float) $allocation->allocated_amount,
                (float) $allocation->used_amount,
                (float) $allocation->balance_amount,
                (string) ($allocation->expires_at?->toDateString() ?? '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function attendanceAnomalyRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        return HrAttendanceRecord::query()
            ->with(['employee:id,display_name,employee_number,user_id'])
            ->where('company_id', $company->id)
            ->accessibleTo($viewer)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query): void {
                $query->whereIn('status', [
                    HrAttendanceRecord::STATUS_ABSENT,
                    HrAttendanceRecord::STATUS_MISSING,
                    HrAttendanceRecord::STATUS_HALF_DAY,
                ])->orWhere('late_minutes', '>', 0)
                    ->orWhere('overtime_minutes', '>', 0);
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('late_minutes')
            ->get()
            ->map(fn (HrAttendanceRecord $record) => [
                (string) ($record->attendance_date?->toDateString() ?? '-'),
                (string) ($record->employee?->employee_number ?? '-'),
                (string) ($record->employee?->display_name ?? '-'),
                str_replace('_', ' ', (string) $record->status),
                (int) $record->worked_minutes,
                (int) $record->late_minutes,
                (int) $record->overtime_minutes,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function reimbursementAgingRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        return HrReimbursementClaim::query()
            ->with(['employee:id,display_name', 'currency:id,code'])
            ->where('company_id', $company->id)
            ->accessibleTo($viewer)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [
                HrReimbursementClaim::STATUS_SUBMITTED,
                HrReimbursementClaim::STATUS_MANAGER_APPROVED,
                HrReimbursementClaim::STATUS_FINANCE_APPROVED,
                HrReimbursementClaim::STATUS_POSTED,
            ])
            ->orderByDesc('submitted_at')
            ->get()
            ->map(function (HrReimbursementClaim $claim): array {
                $anchor = $claim->submitted_at ?? $claim->created_at;

                return [
                    (string) $claim->claim_number,
                    (string) ($claim->employee?->display_name ?? '-'),
                    str_replace('_', ' ', (string) $claim->status),
                    (string) ($claim->currency?->code ?? '-'),
                    (float) $claim->total_amount,
                    (string) ($claim->submitted_at?->toDateString() ?? '-'),
                    $anchor ? (int) $anchor->diffInDays(now()) : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function payrollRegisterRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        return HrPayslip::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'payrollPeriod:id,name,start_date,end_date',
            ])
            ->where('company_id', $company->id)
            ->accessibleTo($viewer)
            ->whereHas('payrollPeriod', function ($query) use ($start, $end): void {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (HrPayslip $payslip) => [
                (string) $payslip->payslip_number,
                (string) ($payslip->employee?->employee_number ?? '-'),
                (string) ($payslip->employee?->display_name ?? '-'),
                (string) ($payslip->payrollPeriod?->name ?? '-'),
                str_replace('_', ' ', (string) $payslip->status),
                (float) $payslip->gross_pay,
                (float) $payslip->total_deductions,
                (float) $payslip->reimbursement_amount,
                (float) $payslip->net_pay,
                (string) ($payslip->issued_at?->toDateString() ?? '-'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<int, string|int|float>>
     */
    private function payslipSummaryRows(Company $company, User $viewer, array $filters): array
    {
        [$start, $end] = $this->dateRange($filters);

        $payslips = HrPayslip::query()
            ->where('company_id', $company->id)
            ->accessibleTo($viewer)
            ->whereHas('payrollPeriod', function ($query) use ($start, $end): void {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            });

        $runs = HrPayrollRun::query()
            ->where('company_id', $company->id)
            ->whereHas('payrollPeriod', function ($query) use ($start, $end): void {
                $query->whereDate('start_date', '<=', $end->toDateString())
                    ->whereDate('end_date', '>=', $start->toDateString());
            });

        return [
            ['Payroll runs', (clone $runs)->count()],
            ['Prepared payroll runs', (clone $runs)->where('status', HrPayrollRun::STATUS_PREPARED)->count()],
            ['Approved payroll runs', (clone $runs)->where('status', HrPayrollRun::STATUS_APPROVED)->count()],
            ['Posted payroll runs', (clone $runs)->where('status', HrPayrollRun::STATUS_POSTED)->count()],
            ['Payslips generated', (clone $payslips)->count()],
            ['Payslips published', (clone $payslips)->whereNotNull('published_at')->count()],
            ['Total gross pay', round((float) (clone $payslips)->sum('gross_pay'), 2)],
            ['Total deductions', round((float) (clone $payslips)->sum('total_deductions'), 2)],
            ['Total reimbursements', round((float) (clone $payslips)->sum('reimbursement_amount'), 2)],
            ['Total net pay', round((float) (clone $payslips)->sum('net_pay'), 2)],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function subtitle(array $filters): string
    {
        [$start, $end] = $this->dateRange($filters);

        return 'Window: '.$start->toDateString().' to '.$end->toDateString();
    }
}
