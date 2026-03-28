<?php

use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Support\Facades\Schema;

test('hr leave tables exist and leave relations persist', function () {
    foreach ([
        'hr_leave_types',
        'hr_leave_periods',
        'hr_leave_allocations',
        'hr_leave_requests',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'employee_number' => 'EMP-LV-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Liya',
        'last_name' => 'Worku',
        'display_name' => 'Liya Worku',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $leaveType = HrLeaveType::create([
        'company_id' => $company->id,
        'name' => 'Annual Leave',
        'code' => 'AL',
        'unit' => HrLeaveType::UNIT_DAYS,
        'requires_allocation' => true,
        'is_paid' => true,
        'requires_approval' => true,
        'allow_negative_balance' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $leavePeriod = HrLeavePeriod::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $allocation = HrLeaveAllocation::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'allocated_amount' => 15,
        'used_amount' => 2,
        'balance_amount' => 13,
        'carry_forward_amount' => 0,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $leaveRequest = HrLeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'requested_by_user_id' => $user->id,
        'request_number' => 'LEV-0001',
        'status' => HrLeaveRequest::STATUS_DRAFT,
        'from_date' => '2026-04-01',
        'to_date' => '2026-04-02',
        'duration_amount' => 2,
        'is_half_day' => false,
        'payroll_status' => HrLeaveRequest::PAYROLL_STATUS_OPEN,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($allocation->employee?->is($employee))->toBeTrue();
    expect($allocation->leaveType?->is($leaveType))->toBeTrue();
    expect($allocation->leavePeriod?->is($leavePeriod))->toBeTrue();
    expect($leaveRequest->employee?->is($employee))->toBeTrue();
    expect($leaveRequest->leaveType?->is($leaveType))->toBeTrue();
    expect($leaveRequest->leavePeriod?->is($leavePeriod))->toBeTrue();
});
