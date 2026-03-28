<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrPayslipLine;
use App\Modules\Hr\Models\HrReimbursementCategory;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Database\Seeders\CoreRolesSeeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function assignHrApiRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $membership = $user->memberships()->where('company_id', $companyId)->first();

    if ($membership) {
        $membership->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);
    } else {
        $user->memberships()->create([
            'company_id' => $companyId,
            'role_id' => $role->id,
            'is_owner' => false,
        ]);
    }

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

function createHrApiEmployee(array $attributes): HrEmployee
{
    return HrEmployee::create([
        'company_id' => $attributes['company_id'],
        'user_id' => $attributes['user_id'] ?? null,
        'employee_number' => $attributes['employee_number'],
        'employment_status' => $attributes['employment_status'] ?? HrEmployee::STATUS_ACTIVE,
        'employment_type' => $attributes['employment_type'] ?? HrEmployee::TYPE_FULL_TIME,
        'first_name' => $attributes['first_name'],
        'last_name' => $attributes['last_name'],
        'display_name' => $attributes['display_name'],
        'work_email' => $attributes['work_email'],
        'hire_date' => $attributes['hire_date'] ?? now()->toDateString(),
        'manager_employee_id' => $attributes['manager_employee_id'] ?? null,
        'attendance_approver_user_id' => $attributes['attendance_approver_user_id'] ?? null,
        'leave_approver_user_id' => $attributes['leave_approver_user_id'] ?? null,
        'reimbursement_approver_user_id' => $attributes['reimbursement_approver_user_id'] ?? null,
        'timezone' => $attributes['timezone'] ?? 'Africa/Nairobi',
        'created_by' => $attributes['created_by'],
        'updated_by' => $attributes['updated_by'] ?? $attributes['created_by'],
    ]);
}

function createHrApiCurrency(string $companyId, string $userId, string $code = 'HRA'): Currency
{
    return Currency::create([
        'company_id' => $companyId,
        'code' => $code,
        'name' => 'HR API Currency '.$code,
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $userId,
        'updated_by' => $userId,
    ]);
}

test('api v1 hr endpoints support profile leave and attendance workflows', function () {
    $this->seed(CoreRolesSeeder::class);

    [$managerUser, $company] = makeActiveCompanyMember();
    assignHrApiRole($managerUser, $company->id, 'line_manager');

    $employeeUser = User::factory()->create();
    assignHrApiRole($employeeUser, $company->id, 'employee_self_service');

    $managerEmployee = createHrApiEmployee([
        'company_id' => $company->id,
        'user_id' => $managerUser->id,
        'employee_number' => 'EMP-HR-API-MGR',
        'first_name' => 'Liya',
        'last_name' => 'Manager',
        'display_name' => 'Liya Manager',
        'work_email' => 'liya.manager@example.test',
        'created_by' => $managerUser->id,
    ]);

    $employee = createHrApiEmployee([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-API-001',
        'first_name' => 'Noah',
        'last_name' => 'Employee',
        'display_name' => 'Noah Employee',
        'work_email' => 'noah.employee@example.test',
        'manager_employee_id' => $managerEmployee->id,
        'attendance_approver_user_id' => $managerUser->id,
        'leave_approver_user_id' => $managerUser->id,
        'reimbursement_approver_user_id' => $managerUser->id,
        'created_by' => $managerUser->id,
    ]);

    $leaveType = HrLeaveType::create([
        'company_id' => $company->id,
        'name' => 'Annual Leave',
        'code' => 'AL',
        'unit' => HrLeaveType::UNIT_DAYS,
        'requires_allocation' => true,
        'requires_approval' => true,
        'is_paid' => true,
        'allow_negative_balance' => false,
        'created_by' => $managerUser->id,
        'updated_by' => $managerUser->id,
    ]);

    $leavePeriod = HrLeavePeriod::create([
        'company_id' => $company->id,
        'name' => '2026',
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'is_closed' => false,
        'created_by' => $managerUser->id,
        'updated_by' => $managerUser->id,
    ]);

    HrLeaveAllocation::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'allocated_amount' => 15,
        'used_amount' => 0,
        'balance_amount' => 15,
        'carry_forward_amount' => 0,
        'created_by' => $managerUser->id,
        'updated_by' => $managerUser->id,
    ]);

    Sanctum::actingAs($employeeUser);

    getJson('/api/v1/hr/me')
        ->assertOk()
        ->assertJsonPath('data.employee.employee_number', 'EMP-HR-API-001')
        ->assertJsonPath('data.employee.manager_name', 'Liya Manager');

    $leaveResponse = postJson('/api/v1/hr/leave/requests', [
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'from_date' => now()->addDays(2)->toDateString(),
        'to_date' => now()->addDays(3)->toDateString(),
        'duration_amount' => 2,
        'is_half_day' => false,
        'reason' => 'Family trip',
        'action' => 'submit',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', HrLeaveRequest::STATUS_SUBMITTED);

    $leaveRequestId = (string) $leaveResponse->json('data.id');

    getJson('/api/v1/hr/leave/requests?status=submitted')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $leaveRequestId)
        ->assertJsonPath('meta.filters.status', HrLeaveRequest::STATUS_SUBMITTED);

    $workDate = now()->toDateString();

    postJson('/api/v1/hr/attendance/check-in', [
        'employee_id' => $employee->id,
        'recorded_at' => $workDate.' 09:05:00',
        'source' => 'web',
    ])
        ->assertOk()
        ->assertJsonPath('data.employee_number', 'EMP-HR-API-001');

    postJson('/api/v1/hr/attendance/check-out', [
        'employee_id' => $employee->id,
        'recorded_at' => $workDate.' 18:10:00',
        'source' => 'web',
    ])
        ->assertOk()
        ->assertJsonPath('data.worked_minutes', 545);

    getJson('/api/v1/hr/attendance/records?start_date='.$workDate.'&end_date='.$workDate)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee_id', $employee->id)
        ->assertJsonPath('meta.filters.start_date', $workDate);

    postJson('/api/v1/hr/attendance/requests', [
        'employee_id' => $employee->id,
        'from_date' => $workDate,
        'to_date' => $workDate,
        'requested_status' => HrAttendanceRecord::STATUS_PRESENT,
        'requested_check_in_at' => '09:00',
        'requested_check_out_at' => '18:00',
        'reason' => 'Correct the morning punch.',
        'action' => 'submit',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted');

    Sanctum::actingAs($managerUser);

    postJson('/api/v1/hr/leave/requests/'.$leaveRequestId.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', HrLeaveRequest::STATUS_APPROVED)
        ->assertJsonPath('data.approved_by_name', $managerUser->name);
});

test('api v1 hr reimbursement and payslip endpoints support approval and self-service reads', function () {
    $this->seed(CoreRolesSeeder::class);

    [$managerUser, $company] = makeActiveCompanyMember();
    assignHrApiRole($managerUser, $company->id, 'line_manager');

    $payrollUser = User::factory()->create();
    assignHrApiRole($payrollUser, $company->id, 'payroll_manager');

    $employeeUser = User::factory()->create();
    assignHrApiRole($employeeUser, $company->id, 'employee_self_service');

    $managerEmployee = createHrApiEmployee([
        'company_id' => $company->id,
        'user_id' => $managerUser->id,
        'employee_number' => 'EMP-HR-API-RMB-MGR',
        'first_name' => 'Marta',
        'last_name' => 'Lead',
        'display_name' => 'Marta Lead',
        'work_email' => 'marta.lead@example.test',
        'created_by' => $managerUser->id,
    ]);

    $employee = createHrApiEmployee([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-API-RMB-001',
        'first_name' => 'Abel',
        'last_name' => 'Analyst',
        'display_name' => 'Abel Analyst',
        'work_email' => 'abel.analyst@example.test',
        'manager_employee_id' => $managerEmployee->id,
        'reimbursement_approver_user_id' => $managerUser->id,
        'created_by' => $managerUser->id,
    ]);

    $currency = createHrApiCurrency($company->id, $managerUser->id, 'HRB');

    $category = HrReimbursementCategory::create([
        'company_id' => $company->id,
        'name' => 'Travel',
        'code' => 'TRAVEL',
        'requires_receipt' => false,
        'is_project_rebillable' => false,
        'created_by' => $managerUser->id,
        'updated_by' => $managerUser->id,
    ]);

    Sanctum::actingAs($employeeUser);

    $claimResponse = postJson('/api/v1/hr/reimbursements', [
        'employee_id' => $employee->id,
        'currency_id' => $currency->id,
        'project_id' => '',
        'notes' => 'Taxi and meals',
        'action' => 'submit',
        'lines' => [[
            'category_id' => $category->id,
            'expense_date' => now()->subDay()->toDateString(),
            'description' => 'Airport transfer',
            'amount' => 85.5,
            'tax_amount' => 4.5,
            'project_id' => '',
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', HrReimbursementClaim::STATUS_SUBMITTED)
        ->assertJsonPath('data.lines_count', 1);

    $claimId = (string) $claimResponse->json('data.id');

    Sanctum::actingAs($managerUser);

    postJson('/api/v1/hr/reimbursements/'.$claimId.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', HrReimbursementClaim::STATUS_MANAGER_APPROVED);

    Sanctum::actingAs($payrollUser);

    postJson('/api/v1/hr/reimbursements/'.$claimId.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', HrReimbursementClaim::STATUS_FINANCE_APPROVED);

    $payrollPeriod = HrPayrollPeriod::create([
        'company_id' => $company->id,
        'name' => 'March 2026',
        'pay_frequency' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'payment_date' => now()->endOfMonth()->toDateString(),
        'status' => HrPayrollPeriod::STATUS_PROCESSING,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $payrollRun = HrPayrollRun::create([
        'company_id' => $company->id,
        'payroll_period_id' => $payrollPeriod->id,
        'run_number' => 'PR-HR-API-001',
        'status' => HrPayrollRun::STATUS_POSTED,
        'prepared_by_user_id' => $payrollUser->id,
        'approved_by_user_id' => $payrollUser->id,
        'posted_by_user_id' => $payrollUser->id,
        'total_gross' => 1800,
        'total_deductions' => 250,
        'total_net' => 1550,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    $payslip = HrPayslip::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'payroll_run_id' => $payrollRun->id,
        'payroll_period_id' => $payrollPeriod->id,
        'currency_id' => $currency->id,
        'payslip_number' => 'PS-HR-API-001',
        'status' => HrPayslip::STATUS_POSTED,
        'gross_pay' => 1800,
        'total_deductions' => 250,
        'reimbursement_amount' => 90,
        'net_pay' => 1640,
        'issued_at' => now()->subDay(),
        'published_at' => now()->subDay(),
        'published_by_user_id' => $payrollUser->id,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    HrPayslipLine::create([
        'company_id' => $company->id,
        'payslip_id' => $payslip->id,
        'line_type' => 'earning',
        'code' => 'BASIC',
        'name' => 'Basic pay',
        'line_order' => 10,
        'quantity' => 1,
        'rate' => 1800,
        'amount' => 1800,
        'created_by' => $payrollUser->id,
        'updated_by' => $payrollUser->id,
    ]);

    Sanctum::actingAs($employeeUser);

    getJson('/api/v1/hr/reimbursements?status=finance_approved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $claimId)
        ->assertJsonPath('meta.filters.status', HrReimbursementClaim::STATUS_FINANCE_APPROVED);

    getJson('/api/v1/hr/payslips')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $payslip->id)
        ->assertJsonPath('data.0.net_pay', 1640);

    getJson('/api/v1/hr/payslips/'.$payslip->id)
        ->assertOk()
        ->assertJsonPath('data.payslip_number', 'PS-HR-API-001')
        ->assertJsonCount(1, 'data.lines')
        ->assertJsonPath('data.lines.0.code', 'BASIC');
});

test('api v1 hr endpoints enforce permissions and company scoping', function () {
    $this->seed(CoreRolesSeeder::class);

    [$memberUser, $company] = makeActiveCompanyMember();
    assignHrApiRole($memberUser, $company->id, 'member');

    [$selfServiceUser, $selfServiceCompany] = makeActiveCompanyMember();
    assignHrApiRole($selfServiceUser, $selfServiceCompany->id, 'employee_self_service');

    $otherEmployee = createHrApiEmployee([
        'company_id' => $selfServiceCompany->id,
        'user_id' => $selfServiceUser->id,
        'employee_number' => 'EMP-HR-API-OTH-001',
        'first_name' => 'Other',
        'last_name' => 'Company',
        'display_name' => 'Other Company',
        'work_email' => 'other.company@example.test',
        'created_by' => $selfServiceUser->id,
    ]);

    $otherCurrency = createHrApiCurrency($selfServiceCompany->id, $selfServiceUser->id, 'HRC');

    $otherPeriod = HrPayrollPeriod::create([
        'company_id' => $selfServiceCompany->id,
        'name' => 'Other March 2026',
        'pay_frequency' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'payment_date' => now()->endOfMonth()->toDateString(),
        'status' => HrPayrollPeriod::STATUS_PROCESSING,
        'created_by' => $selfServiceUser->id,
        'updated_by' => $selfServiceUser->id,
    ]);

    $otherRun = HrPayrollRun::create([
        'company_id' => $selfServiceCompany->id,
        'payroll_period_id' => $otherPeriod->id,
        'run_number' => 'PR-HR-API-OTH-001',
        'status' => HrPayrollRun::STATUS_POSTED,
        'prepared_by_user_id' => $selfServiceUser->id,
        'approved_by_user_id' => $selfServiceUser->id,
        'posted_by_user_id' => $selfServiceUser->id,
        'total_gross' => 1000,
        'total_deductions' => 100,
        'total_net' => 900,
        'created_by' => $selfServiceUser->id,
        'updated_by' => $selfServiceUser->id,
    ]);

    $otherPayslip = HrPayslip::create([
        'company_id' => $selfServiceCompany->id,
        'employee_id' => $otherEmployee->id,
        'payroll_run_id' => $otherRun->id,
        'payroll_period_id' => $otherPeriod->id,
        'currency_id' => $otherCurrency->id,
        'payslip_number' => 'PS-HR-API-OTH-001',
        'status' => HrPayslip::STATUS_POSTED,
        'gross_pay' => 1000,
        'total_deductions' => 100,
        'reimbursement_amount' => 0,
        'net_pay' => 900,
        'issued_at' => now()->subDay(),
        'published_at' => now()->subDay(),
        'created_by' => $selfServiceUser->id,
        'updated_by' => $selfServiceUser->id,
    ]);

    Sanctum::actingAs($memberUser);

    getJson('/api/v1/hr/me')
        ->assertForbidden();

    Sanctum::actingAs($memberUser);

    getJson('/api/v1/hr/payslips/'.$otherPayslip->id)
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource not found.');
});
