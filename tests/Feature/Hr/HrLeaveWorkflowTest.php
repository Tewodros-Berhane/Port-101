<?php

use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeaveRequest;
use Database\Seeders\CoreRolesSeeder;

use function Pest\Laravel\actingAs;

function assignHrLeaveRole(User $user, string $companyId, string $roleSlug): void
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

test('self service employee can submit leave and hr manager can approve it with balance updates', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignHrLeaveRole($manager, $company->id, 'hr_manager');

    $employeeUser = User::factory()->create();
    $company->users()->syncWithoutDetaching([
        $employeeUser->id => [
            'role_id' => null,
            'is_owner' => false,
        ],
    ]);
    assignHrLeaveRole($employeeUser, $company->id, 'employee_self_service');

    $managerEmployee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $manager->id,
        'employee_number' => 'EMP-HR-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Meron',
        'last_name' => 'Manager',
        'display_name' => 'Meron Manager',
        'hire_date' => now()->toDateString(),
        'timezone' => 'UTC',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-002',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Abel',
        'last_name' => 'Worker',
        'display_name' => 'Abel Worker',
        'hire_date' => now()->toDateString(),
        'manager_employee_id' => $managerEmployee->id,
        'leave_approver_user_id' => $manager->id,
        'timezone' => 'UTC',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->post(route('company.hr.leave.types.store'), [
            'name' => 'Annual Leave',
            'code' => 'AL',
            'unit' => 'days',
            'requires_allocation' => true,
            'is_paid' => true,
            'requires_approval' => true,
            'allow_negative_balance' => false,
            'max_consecutive_days' => 10,
            'color' => '#2563eb',
        ])
        ->assertRedirect(route('company.hr.leave.index'));

    actingAs($manager)
        ->post(route('company.hr.leave.periods.store'), [
            'name' => 'FY 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_closed' => false,
        ])
        ->assertRedirect(route('company.hr.leave.index'));

    $leaveTypeId = (string) App\Modules\Hr\Models\HrLeaveType::query()->where('company_id', $company->id)->value('id');
    $leavePeriodId = (string) App\Modules\Hr\Models\HrLeavePeriod::query()->where('company_id', $company->id)->value('id');

    actingAs($manager)
        ->post(route('company.hr.leave.allocations.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveTypeId,
            'leave_period_id' => $leavePeriodId,
            'allocated_amount' => 12,
            'used_amount' => 0,
            'carry_forward_amount' => 0,
            'expires_at' => '',
            'notes' => 'Initial entitlement',
        ])
        ->assertRedirect(route('company.hr.leave.index'));

    actingAs($employeeUser)
        ->post(route('company.hr.leave.requests.store'), [
            'employee_id' => '',
            'leave_type_id' => $leaveTypeId,
            'leave_period_id' => $leavePeriodId,
            'from_date' => '2026-05-12',
            'to_date' => '2026-05-14',
            'duration_amount' => '',
            'is_half_day' => false,
            'reason' => 'Annual family travel.',
            'action' => 'submit',
        ])
        ->assertRedirect(route('company.hr.leave.index'));

    $leaveRequest = HrLeaveRequest::query()
        ->where('employee_id', $employee->id)
        ->latest('created_at')
        ->first();

    expect($leaveRequest)->not->toBeNull();
    expect($leaveRequest?->status)->toBe(HrLeaveRequest::STATUS_SUBMITTED);
    expect((float) $leaveRequest?->duration_amount)->toBe(3.0);

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('module', ApprovalRequest::MODULE_HR)
        ->where('source_id', $leaveRequest?->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($manager)
        ->post(route('company.hr.leave.requests.approve', $leaveRequest))
        ->assertRedirect();

    expect($leaveRequest?->fresh()?->status)->toBe(HrLeaveRequest::STATUS_APPROVED);

    $allocation = HrLeaveAllocation::query()
        ->where('employee_id', $employee->id)
        ->first();

    expect((float) $allocation?->used_amount)->toBe(3.0);
    expect((float) $allocation?->balance_amount)->toBe(9.0);
    expect($approvalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_APPROVED);
});

test('member without leave permissions cannot open leave workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrLeaveRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.leave.index'))
        ->assertForbidden();
});
