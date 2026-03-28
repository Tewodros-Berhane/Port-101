<?php

use App\Core\MasterData\Models\Currency;
use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Hr\HrReportsService;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use App\Modules\Hr\Models\HrPayrollPeriod;
use App\Modules\Hr\Models\HrPayrollRun;
use App\Modules\Hr\Models\HrPayslip;
use App\Modules\Hr\Models\HrReimbursementClaim;
use Database\Seeders\CoreRolesSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

function assignHrReportsRole(User $user, string $companyId, string $roleSlug): void
{
    $role = Role::query()
        ->where('slug', $roleSlug)
        ->whereNull('company_id')
        ->firstOrFail();

    $user->memberships()
        ->where('company_id', $companyId)
        ->update([
            'role_id' => $role->id,
            'is_owner' => false,
        ]);

    $user->forceFill([
        'current_company_id' => $companyId,
    ])->save();
}

test('hr reports page and exports are available for hr reporting users', function () {
    $this->seed(CoreRolesSeeder::class);

    [$hrManager, $company] = makeActiveCompanyMember();
    assignHrReportsRole($hrManager, $company->id, 'hr_manager');

    $employeeUser = User::factory()->create();
    $employeeUser->memberships()->create([
        'company_id' => $company->id,
        'role_id' => Role::query()->where('slug', 'employee_self_service')->whereNull('company_id')->value('id'),
        'is_owner' => false,
    ]);

    $currency = Currency::create([
        'company_id' => $company->id,
        'code' => 'HRR',
        'name' => 'HR Report Currency',
        'symbol' => 'Br',
        'decimal_places' => 2,
        'is_active' => true,
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    $managerEmployee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $hrManager->id,
        'employee_number' => 'EMP-HR-MGR',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Helen',
        'last_name' => 'Manager',
        'display_name' => 'Helen Manager',
        'work_email' => 'helen.manager@example.test',
        'hire_date' => now()->subMonths(8)->toDateString(),
        'timezone' => 'Africa/Nairobi',
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-REP',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Mimi',
        'last_name' => 'Analyst',
        'display_name' => 'Mimi Analyst',
        'work_email' => 'mimi.analyst@example.test',
        'hire_date' => now()->subDays(10)->toDateString(),
        'manager_employee_id' => $managerEmployee->id,
        'leave_approver_user_id' => $hrManager->id,
        'attendance_approver_user_id' => $hrManager->id,
        'reimbursement_approver_user_id' => $hrManager->id,
        'timezone' => 'Africa/Nairobi',
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
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
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    $leavePeriod = HrLeavePeriod::create([
        'company_id' => $company->id,
        'name' => '2026',
        'start_date' => now()->startOfYear()->toDateString(),
        'end_date' => now()->endOfYear()->toDateString(),
        'is_closed' => false,
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    HrLeaveAllocation::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'allocated_amount' => 18,
        'used_amount' => 2,
        'balance_amount' => 16,
        'carry_forward_amount' => 0,
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    HrLeaveRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_period_id' => $leavePeriod->id,
        'requested_by_user_id' => $employeeUser->id,
        'approver_user_id' => $hrManager->id,
        'approved_by_user_id' => $hrManager->id,
        'request_number' => 'LEV-REPORT-001',
        'status' => HrLeaveRequest::STATUS_APPROVED,
        'from_date' => now()->subDays(4)->toDateString(),
        'to_date' => now()->subDays(3)->toDateString(),
        'duration_amount' => 2,
        'is_half_day' => false,
        'payroll_status' => HrLeaveRequest::PAYROLL_STATUS_OPEN,
        'submitted_at' => now()->subDays(5),
        'approved_at' => now()->subDays(5),
        'created_by' => $employeeUser->id,
        'updated_by' => $hrManager->id,
    ]);

    HrAttendanceRecord::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'attendance_date' => now()->subDay()->toDateString(),
        'status' => HrAttendanceRecord::STATUS_MISSING,
        'worked_minutes' => 0,
        'overtime_minutes' => 0,
        'late_minutes' => 35,
        'approval_status' => HrAttendanceRecord::APPROVAL_NOT_REQUIRED,
        'source_summary' => '1 raw log',
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    HrReimbursementClaim::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'currency_id' => $currency->id,
        'claim_number' => 'RMB-REPORT-001',
        'status' => HrReimbursementClaim::STATUS_SUBMITTED,
        'total_amount' => 120.5,
        'requested_by_user_id' => $employeeUser->id,
        'approver_user_id' => $hrManager->id,
        'manager_approver_user_id' => $hrManager->id,
        'submitted_at' => now()->subDays(6),
        'created_by' => $employeeUser->id,
        'updated_by' => $employeeUser->id,
    ]);

    $payrollPeriod = HrPayrollPeriod::create([
        'company_id' => $company->id,
        'name' => 'March 2026',
        'pay_frequency' => 'monthly',
        'start_date' => now()->startOfMonth()->toDateString(),
        'end_date' => now()->endOfMonth()->toDateString(),
        'payment_date' => now()->endOfMonth()->toDateString(),
        'status' => HrPayrollPeriod::STATUS_PROCESSING,
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    $payrollRun = HrPayrollRun::create([
        'company_id' => $company->id,
        'payroll_period_id' => $payrollPeriod->id,
        'run_number' => 'PR-HR-REPORT-001',
        'status' => HrPayrollRun::STATUS_POSTED,
        'prepared_by_user_id' => $hrManager->id,
        'approved_by_user_id' => $hrManager->id,
        'posted_by_user_id' => $hrManager->id,
        'total_gross' => 2500,
        'total_deductions' => 400,
        'total_net' => 2100,
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    HrPayslip::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'payroll_run_id' => $payrollRun->id,
        'payroll_period_id' => $payrollPeriod->id,
        'currency_id' => $currency->id,
        'payslip_number' => 'PS-REPORT-001',
        'status' => HrPayslip::STATUS_POSTED,
        'gross_pay' => 2500,
        'total_deductions' => 400,
        'reimbursement_amount' => 120.5,
        'net_pay' => 2220.5,
        'issued_at' => now()->subDay(),
        'published_at' => now()->subDay(),
        'created_by' => $hrManager->id,
        'updated_by' => $hrManager->id,
    ]);

    actingAs($hrManager)
        ->get(route('company.hr.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('hr/reports/index')
            ->has('reportCatalog', count(HrReportsService::REPORT_KEYS))
            ->where('canExport', true));

    actingAs($hrManager)
        ->get(route('company.hr.reports.export', [
            'reportKey' => HrReportsService::REPORT_EMPLOYEE_DIRECTORY,
            'format' => 'pdf',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    actingAs($hrManager)
        ->get(route('company.hr.reports.export', [
            'reportKey' => HrReportsService::REPORT_PAYROLL_REGISTER,
            'format' => 'xlsx',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('member without hr reports permission cannot open hr reports', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrReportsRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.reports.index'))
        ->assertForbidden();
});
