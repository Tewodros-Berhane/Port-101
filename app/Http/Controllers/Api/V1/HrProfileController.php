<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrPayslip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrProfileController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasPermission('hr.employees.view')) {
            abort(403);
        }

        $employee = HrEmployee::query()
            ->with([
                'department:id,name',
                'designation:id,name',
                'managerEmployee:id,display_name,employee_number,user_id',
                'user:id,name,email',
                'contracts' => fn ($query) => $query->where('status', 'active')->limit(1),
            ])
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->first();

        abort_if(! $employee, 404, 'HR employee profile not found.');

        $this->authorize('view', $employee);

        $leaveBalances = HrLeaveAllocation::query()
            ->with(['leaveType:id,name,unit', 'leavePeriod:id,name'])
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();

        $latestPayslip = HrPayslip::query()
            ->with(['payrollPeriod:id,name,start_date,end_date'])
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at')
            ->first();

        return $this->respond([
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'display_name' => $employee->display_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'employment_status' => $employee->employment_status,
                'employment_type' => $employee->employment_type,
                'work_email' => $employee->work_email,
                'work_phone' => $employee->work_phone,
                'hire_date' => $employee->hire_date?->toDateString(),
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'manager_name' => $employee->managerEmployee?->display_name,
                'manager_employee_number' => $employee->managerEmployee?->employee_number,
                'timezone' => $employee->timezone,
                'work_location' => $employee->work_location,
                'linked_user_name' => $employee->user?->name,
                'linked_user_email' => $employee->user?->email,
            ],
            'active_contract' => ($contract = $employee->contracts->first()) ? [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'status' => $contract->status,
                'start_date' => $contract->start_date?->toDateString(),
                'end_date' => $contract->end_date?->toDateString(),
                'pay_frequency' => $contract->pay_frequency,
                'salary_basis' => $contract->salary_basis,
                'base_salary_amount' => (float) $contract->base_salary_amount,
                'hourly_rate' => $contract->hourly_rate !== null ? (float) $contract->hourly_rate : null,
                'working_days_per_week' => (float) $contract->working_days_per_week,
                'standard_hours_per_day' => (float) $contract->standard_hours_per_day,
                'is_payroll_eligible' => (bool) $contract->is_payroll_eligible,
            ] : null,
            'leave_balances' => $leaveBalances->map(fn (HrLeaveAllocation $allocation) => [
                'id' => $allocation->id,
                'leave_type_name' => $allocation->leaveType?->name,
                'leave_type_unit' => $allocation->leaveType?->unit,
                'leave_period_name' => $allocation->leavePeriod?->name,
                'allocated_amount' => (float) $allocation->allocated_amount,
                'used_amount' => (float) $allocation->used_amount,
                'balance_amount' => (float) $allocation->balance_amount,
                'expires_at' => $allocation->expires_at?->toDateString(),
            ])->values()->all(),
            'latest_payslip' => $latestPayslip ? [
                'id' => $latestPayslip->id,
                'payslip_number' => $latestPayslip->payslip_number,
                'status' => $latestPayslip->status,
                'period_name' => $latestPayslip->payrollPeriod?->name,
                'gross_pay' => (float) $latestPayslip->gross_pay,
                'total_deductions' => (float) $latestPayslip->total_deductions,
                'reimbursement_amount' => (float) $latestPayslip->reimbursement_amount,
                'net_pay' => (float) $latestPayslip->net_pay,
                'issued_at' => $latestPayslip->issued_at?->toIso8601String(),
                'published_at' => $latestPayslip->published_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
