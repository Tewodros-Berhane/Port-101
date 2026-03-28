<?php

namespace App\Modules\Hr;

use App\Core\Company\Models\CompanyUser;
use App\Core\MasterData\Models\Currency;
use App\Models\User;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrSalaryStructure;

class HrPayrollWorkspaceService
{
    public function summary(User $user): array
    {
        $runQuery = HrPayrollRun::query();
        $periodQuery = HrPayrollPeriod::query();
        $payslipQuery = HrPayslip::query()->accessibleTo($user);

        return [
            'open_periods' => (clone $periodQuery)
                ->whereIn('status', [HrPayrollPeriod::STATUS_DRAFT, HrPayrollPeriod::STATUS_OPEN, HrPayrollPeriod::STATUS_PROCESSING])
                ->count(),
            'prepared_runs' => (clone $runQuery)
                ->where('status', HrPayrollRun::STATUS_PREPARED)
                ->count(),
            'pending_my_approvals' => (clone $runQuery)
                ->where('status', HrPayrollRun::STATUS_PREPARED)
                ->where('approver_user_id', $user->id)
                ->count(),
            'posted_30d' => (clone $runQuery)
                ->where('status', HrPayrollRun::STATUS_POSTED)
                ->where('posted_at', '>=', now()->subDays(30))
                ->count(),
            'payslips_30d' => (clone $payslipQuery)
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
            'net_posted_30d' => round((float) (clone $runQuery)
                ->where('status', HrPayrollRun::STATUS_POSTED)
                ->where('posted_at', '>=', now()->subDays(30))
                ->sum('total_net'), 2),
        ];
    }

    public function employeeOptions(User $user): array
    {
        return HrEmployee::query()
            ->accessibleTo($user)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'employee_number', 'user_id'])
            ->map(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->display_name,
                'employee_number' => $employee->employee_number,
                'linked_user_id' => $employee->user_id,
            ])
            ->values()
            ->all();
    }

    public function payrollPeriodOptions(): array
    {
        return HrPayrollPeriod::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'pay_frequency', 'start_date', 'end_date', 'status'])
            ->map(fn (HrPayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'pay_frequency' => $period->pay_frequency,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'status' => $period->status,
            ])
            ->values()
            ->all();
    }

    public function salaryStructureOptions(): array
    {
        return HrSalaryStructure::query()
            ->with('lines:id,salary_structure_id,line_type')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active'])
            ->map(fn (HrSalaryStructure $structure) => [
                'id' => $structure->id,
                'name' => $structure->name,
                'code' => $structure->code,
                'is_active' => (bool) $structure->is_active,
                'line_count' => $structure->lines->count(),
            ])
            ->values()
            ->all();
    }

    public function contractOptions(User $user): array
    {
        return HrEmployeeContract::query()
            ->with('employee:id,display_name,employee_number')
            ->whereHas('employee', fn ($employeeQuery) => $employeeQuery->accessibleTo($user))
            ->orderByDesc('start_date')
            ->get(['id', 'employee_id', 'contract_number', 'status', 'pay_frequency', 'salary_basis', 'currency_id'])
            ->map(fn (HrEmployeeContract $contract) => [
                'id' => $contract->id,
                'employee_id' => $contract->employee_id,
                'contract_number' => $contract->contract_number,
                'employee_name' => $contract->employee?->display_name,
                'employee_number' => $contract->employee?->employee_number,
                'status' => $contract->status,
                'pay_frequency' => $contract->pay_frequency,
                'salary_basis' => $contract->salary_basis,
                'currency_id' => $contract->currency_id,
            ])
            ->values()
            ->all();
    }

    public function companyUserOptions(?string $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->with('user:id,name,email')
            ->get()
            ->filter(fn (CompanyUser $membership) => $membership->user !== null)
            ->map(fn (CompanyUser $membership) => [
                'id' => (string) $membership->user_id,
                'name' => (string) $membership->user?->name,
                'email' => (string) $membership->user?->email,
                'is_owner' => (bool) $membership->is_owner,
            ])
            ->sortByDesc('is_owner')
            ->values()
            ->all();
    }

    public function currencyOptions(string $companyId): array
    {
        return Currency::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'symbol'])
            ->map(fn (Currency $currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
            ])
            ->values()
            ->all();
    }

    public function assignmentRows(User $user): array
    {
        return HrCompensationAssignment::query()
            ->with(['employee:id,display_name,employee_number', 'salaryStructure:id,name,code', 'currency:id,code'])
            ->orderByDesc('effective_from')
            ->limit(20)
            ->get()
            ->filter(fn (HrCompensationAssignment $assignment) => $user->can('view', $assignment))
            ->map(fn (HrCompensationAssignment $assignment) => [
                'id' => $assignment->id,
                'employee_name' => $assignment->employee?->display_name,
                'employee_number' => $assignment->employee?->employee_number,
                'salary_structure_name' => $assignment->salaryStructure?->name,
                'currency_code' => $assignment->currency?->code,
                'pay_frequency' => $assignment->pay_frequency,
                'salary_basis' => $assignment->salary_basis,
                'base_salary_amount' => $assignment->base_salary_amount !== null ? (float) $assignment->base_salary_amount : null,
                'hourly_rate' => $assignment->hourly_rate !== null ? (float) $assignment->hourly_rate : null,
                'effective_from' => $assignment->effective_from?->toDateString(),
                'effective_to' => $assignment->effective_to?->toDateString(),
                'is_active' => (bool) $assignment->is_active,
            ])
            ->values()
            ->all();
    }
}
