<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingAccount;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrCompensationAssignment;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrEmployeeContract;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Database\Seeders\CoreRolesSeeder;

use function Pest\Laravel\actingAs;

function assignHrPayrollRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()->where('slug', $roleSlug)->whereNull('company_id')->firstOrFail();

    $user->memberships()->where('company_id', $companyId)->update([
        'role_id' => $role->id,
        'is_owner' => false,
    ]);

    $user->forceFill(['current_company_id' => $companyId])->save();
}

test('payroll manager can prepare approve and post payroll run with accounting handoff', function () {
    $this->seed(CoreRolesSeeder::class);

    [$payrollUser, $company] = makeActiveCompanyMember();
    assignHrPayrollRole($payrollUser, $company->id, 'payroll_manager');

    $approverUser = User::factory()->create();
    $employeeUser = User::factory()->create();

    $company->users()->syncWithoutDetaching([
        $approverUser->id => ['role_id' => null, 'is_owner' => false],
        $employeeUser->id => ['role_id' => null, 'is_owner' => false],
    ]);

    assignHrPayrollRole($approverUser, $company->id, 'hr_manager');
    assignHrPayrollRole($employeeUser, $company->id, 'employee_self_service');

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => (string) ($company->currency_code ?: 'USD'),
        'name' => 'Company Currency',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $managerEmployee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $approverUser->id,
        'employee_number' => 'EMP-HR-PAY-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Paula',
        'last_name' => 'Approver',
        'display_name' => 'Paula Approver',
        'hire_date' => '2026-01-01',
        'timezone' => 'UTC',
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-PAY-002',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Erin',
        'last_name' => 'Worker',
        'display_name' => 'Erin Worker',
        'hire_date' => '2026-01-01',
        'manager_employee_id' => $managerEmployee->id,
        'attendance_approver_user_id' => $approverUser->id,
        'leave_approver_user_id' => $approverUser->id,
        'reimbursement_approver_user_id' => $approverUser->id,
        'timezone' => 'UTC',
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $contract = HrEmployeeContract::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'contract_number' => 'CON-HR-PAY-001',
        'status' => HrEmployeeContract::STATUS_ACTIVE,
        'start_date' => '2026-01-01',
        'pay_frequency' => HrEmployeeContract::PAY_FREQUENCY_MONTHLY,
        'salary_basis' => HrEmployeeContract::SALARY_BASIS_FIXED,
        'base_salary_amount' => 2000,
        'hourly_rate' => null,
        'currency_id' => $currency->id,
        'working_days_per_week' => 5,
        'standard_hours_per_day' => 8,
        'is_payroll_eligible' => true,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.structures.store'), [
            'name' => 'Standard Monthly',
            'code' => 'STD-MONTHLY',
            'is_active' => true,
            'notes' => 'Standard monthly payroll structure',
            'lines' => [[
                'line_type' => 'deduction',
                'calculation_type' => 'fixed',
                'code' => 'TAX',
                'name' => 'Tax withholding',
                'amount' => 120,
                'percentage_rate' => '',
                'is_active' => true,
            ]],
        ])
        ->assertRedirect(route('company.hr.payroll.index'));

    $structure = App\Modules\Hr\Models\HrSalaryStructure::query()->where('company_id', $company->id)->firstOrFail();

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.assignments.store'), [
            'employee_id' => $employee->id,
            'contract_id' => $contract->id,
            'salary_structure_id' => $structure->id,
            'currency_id' => $currency->id,
            'effective_from' => '2026-03-01',
            'effective_to' => '',
            'pay_frequency' => 'monthly',
            'salary_basis' => 'fixed',
            'base_salary_amount' => 2000,
            'hourly_rate' => '',
            'payroll_group' => 'HQ',
            'is_active' => true,
            'notes' => 'Primary payroll assignment',
        ])
        ->assertRedirect(route('company.hr.payroll.index'));

    expect(HrCompensationAssignment::query()->where('company_id', $company->id)->count())->toBe(1);

    $leaveType = HrLeaveType::create([
        'company_id' => $company->id,
        'name' => 'Unpaid Leave',
        'code' => 'UNPAID',
        'unit' => HrLeaveType::UNIT_DAYS,
        'requires_allocation' => false,
        'is_paid' => false,
        'requires_approval' => true,
        'allow_negative_balance' => true,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $leavePeriod = HrLeavePeriod::create([
        'company_id' => $company->id,
        'name' => 'FY26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_closed' => false,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    HrAttendanceRecord::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-03-11',
        'status' => HrAttendanceRecord::STATUS_PRESENT,
        'check_in_at' => '2026-03-11 09:00:00',
        'check_out_at' => '2026-03-11 18:00:00',
        'worked_minutes' => 480,
        'overtime_minutes' => 60,
        'late_minutes' => 0,
        'approval_status' => HrAttendanceRecord::APPROVAL_APPROVED,
        'approved_by_user_id' => $approverUser->id,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $leaveRequest = HrLeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'requested_by_user_id' => $employeeUser->id,
        'approver_user_id' => $approverUser->id,
        'approved_by_user_id' => $approverUser->id,
        'request_number' => 'LVE-PAY-001',
        'status' => HrLeaveRequest::STATUS_APPROVED,
        'from_date' => '2026-03-17',
        'to_date' => '2026-03-17',
        'duration_amount' => 1,
        'is_half_day' => false,
        'reason' => 'Personal errand',
        'payroll_status' => HrLeaveRequest::PAYROLL_STATUS_OPEN,
        'submitted_at' => now(),
        'approved_at' => now(),
        'created_by' => $employeeUser->id,
        'updated_by' => $approverUser->id,
    ]);

    $claim = HrReimbursementClaim::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'currency_id' => $currency->id,
        'claim_number' => 'RMB-PAY-001',
        'status' => HrReimbursementClaim::STATUS_FINANCE_APPROVED,
        'total_amount' => 150,
        'requested_by_user_id' => $employeeUser->id,
        'approver_user_id' => $approverUser->id,
        'manager_approver_user_id' => $approverUser->id,
        'finance_approver_user_id' => $approverUser->id,
        'manager_approved_by_user_id' => $approverUser->id,
        'finance_approved_by_user_id' => $approverUser->id,
        'approved_by_user_id' => $approverUser->id,
        'notes' => 'Travel expense',
        'submitted_at' => now()->subDays(3),
        'manager_approved_at' => now()->subDays(2),
        'finance_approved_at' => now()->subDay(),
        'approved_at' => now()->subDay(),
        'created_by' => $employeeUser->id,
        'updated_by' => $approverUser->id,
    ]);

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.periods.store'), [
            'name' => 'March 2026',
            'pay_frequency' => 'monthly',
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'payment_date' => '2026-03-31',
            'status' => HrPayrollPeriod::STATUS_OPEN,
        ])
        ->assertRedirect(route('company.hr.payroll.index'));

    $period = HrPayrollPeriod::query()->where('company_id', $company->id)->firstOrFail();

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.runs.store'), [
            'payroll_period_id' => $period->id,
            'approver_user_id' => $approverUser->id,
        ])
        ->assertRedirect();

    $run = HrPayrollRun::query()->where('company_id', $company->id)->firstOrFail();

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.runs.prepare', $run))
        ->assertRedirect();

    $run = $run->fresh(['workEntries', 'payslips.lines']);

    expect($run?->status)->toBe(HrPayrollRun::STATUS_PREPARED);
    expect($run?->workEntries)->toHaveCount(4);
    expect($run?->payslips)->toHaveCount(1);
    expect((float) $run?->total_reimbursements)->toBe(150.0);
    expect((float) $run?->total_net)->toBeGreaterThan(0);

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('module', ApprovalRequest::MODULE_HR)
        ->where('action', ApprovalRequest::ACTION_HR_PAYROLL_APPROVAL)
        ->where('source_id', $run->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($approverUser)
        ->post(route('company.hr.payroll.runs.approve', $run))
        ->assertRedirect();

    expect($run->fresh()?->status)->toBe(HrPayrollRun::STATUS_APPROVED);
    expect($approvalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    actingAs($payrollUser)
        ->post(route('company.hr.payroll.runs.post', $run))
        ->assertRedirect();

    $run = $run->fresh(['accountingManualJournal', 'payslips']);
    $payslip = $run?->payslips?->first();

    expect($run?->status)->toBe(HrPayrollRun::STATUS_POSTED);
    expect($run?->accountingManualJournal)->not->toBeNull();
    expect($run?->accountingManualJournal?->status)->toBe(AccountingManualJournal::STATUS_POSTED);
    expect($payslip)->not->toBeNull();
    expect($payslip?->status)->toBe(HrPayslip::STATUS_POSTED);
    expect($leaveRequest->fresh()?->payroll_status)->toBe(HrLeaveRequest::PAYROLL_STATUS_CONSUMED);
    expect($claim->fresh()?->status)->toBe(HrReimbursementClaim::STATUS_POSTED);
    expect((string) ($claim->fresh()?->payslip_id ?? ''))->toBe((string) $payslip?->id);

    expect(AccountingAccount::query()->where('company_id', $company->id)->where('system_key', AccountingAccount::SYSTEM_PAYROLL_EXPENSE)->exists())->toBeTrue();
    expect(AccountingAccount::query()->where('company_id', $company->id)->where('system_key', AccountingAccount::SYSTEM_PAYROLL_PAYABLE)->exists())->toBeTrue();

    actingAs($employeeUser)
        ->get(route('company.hr.payroll.index'))
        ->assertOk();

    actingAs($employeeUser)
        ->get(route('company.hr.payroll.payslips.show', $payslip))
        ->assertOk();
});

test('member without payroll permissions cannot open payroll workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrPayrollRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.payroll.index'))
        ->assertForbidden();
});
