<?php

use App\Core\RBAC\Models\Role;
use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrShift;
use Database\Seeders\CoreRolesSeeder;

use function Pest\Laravel\actingAs;

function assignHrAttendanceRole(User $user, string $companyId, string $roleSlug): void
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

test('self service employee can punch attendance and submit correction request for approval', function () {
    $this->seed(CoreRolesSeeder::class);

    [$manager, $company] = makeActiveCompanyMember();
    assignHrAttendanceRole($manager, $company->id, 'hr_manager');

    $employeeUser = User::factory()->create();
    $company->users()->syncWithoutDetaching([
        $employeeUser->id => [
            'role_id' => null,
            'is_owner' => false,
        ],
    ]);
    assignHrAttendanceRole($employeeUser, $company->id, 'employee_self_service');

    $managerEmployee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $manager->id,
        'employee_number' => 'EMP-HR-ATT-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Mimi',
        'last_name' => 'Manager',
        'display_name' => 'Mimi Manager',
        'hire_date' => '2026-01-01',
        'timezone' => 'UTC',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $employeeUser->id,
        'employee_number' => 'EMP-HR-ATT-002',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Helen',
        'last_name' => 'Worker',
        'display_name' => 'Helen Worker',
        'hire_date' => '2026-01-01',
        'manager_employee_id' => $managerEmployee->id,
        'attendance_approver_user_id' => $manager->id,
        'timezone' => 'UTC',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    actingAs($manager)
        ->post(route('company.hr.attendance.shifts.store'), [
            'name' => 'Main Shift',
            'code' => 'SHIFT-A',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 10,
            'auto_attendance_enabled' => false,
        ])
        ->assertRedirect(route('company.hr.attendance.index'));

    $shift = HrShift::query()->where('company_id', $company->id)->firstOrFail();

    actingAs($manager)
        ->post(route('company.hr.attendance.assignments.store'), [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'from_date' => '2026-03-01',
            'to_date' => '',
        ])
        ->assertRedirect(route('company.hr.attendance.index'));

    actingAs($employeeUser)
        ->post(route('company.hr.attendance.check-in'), [
            'employee_id' => '',
            'recorded_at' => '2026-03-28 09:20:00',
            'source' => 'web',
        ])
        ->assertRedirect();

    actingAs($employeeUser)
        ->post(route('company.hr.attendance.check-out'), [
            'employee_id' => '',
            'recorded_at' => '2026-03-28 18:10:00',
            'source' => 'web',
        ])
        ->assertRedirect();

    $attendanceRecord = HrAttendanceRecord::query()
        ->where('company_id', $company->id)
        ->where('employee_id', $employee->id)
        ->whereDate('attendance_date', '2026-03-28')
        ->first();

    expect($attendanceRecord)->not->toBeNull();
    expect($attendanceRecord?->status)->toBe(HrAttendanceRecord::STATUS_PRESENT);
    expect((int) $attendanceRecord?->worked_minutes)->toBe(530);
    expect((int) $attendanceRecord?->late_minutes)->toBe(10);
    expect((int) $attendanceRecord?->overtime_minutes)->toBe(70);

    actingAs($employeeUser)
        ->post(route('company.hr.attendance.requests.store'), [
            'employee_id' => '',
            'from_date' => '2026-03-27',
            'to_date' => '2026-03-27',
            'requested_status' => HrAttendanceRecord::STATUS_PRESENT,
            'requested_check_in_at' => '09:05',
            'requested_check_out_at' => '17:02',
            'reason' => 'Missed the punch due to network interruption.',
            'action' => 'submit',
        ])
        ->assertRedirect(route('company.hr.attendance.index'));

    $attendanceRequest = HrAttendanceRequest::query()
        ->where('employee_id', $employee->id)
        ->latest('created_at')
        ->first();

    expect($attendanceRequest)->not->toBeNull();
    expect($attendanceRequest?->status)->toBe(HrAttendanceRequest::STATUS_SUBMITTED);

    $approvalRequest = ApprovalRequest::query()
        ->where('company_id', $company->id)
        ->where('module', ApprovalRequest::MODULE_HR)
        ->where('action', ApprovalRequest::ACTION_HR_ATTENDANCE_APPROVAL)
        ->where('source_id', $attendanceRequest?->id)
        ->first();

    expect($approvalRequest)->not->toBeNull();
    expect($approvalRequest?->status)->toBe(ApprovalRequest::STATUS_PENDING);

    actingAs($manager)
        ->post(route('company.hr.attendance.requests.approve', $attendanceRequest))
        ->assertRedirect();

    expect($attendanceRequest?->fresh()?->status)->toBe(HrAttendanceRequest::STATUS_APPROVED);
    expect($approvalRequest?->fresh()?->status)->toBe(ApprovalRequest::STATUS_APPROVED);

    $correctedRecord = HrAttendanceRecord::query()
        ->where('company_id', $company->id)
        ->where('employee_id', $employee->id)
        ->whereDate('attendance_date', '2026-03-27')
        ->first();

    expect($correctedRecord)->not->toBeNull();
    expect($correctedRecord?->status)->toBe(HrAttendanceRecord::STATUS_PRESENT);
    expect((int) $correctedRecord?->worked_minutes)->toBe(477);
});

test('member without attendance permissions cannot open attendance workspace', function () {
    $this->seed(CoreRolesSeeder::class);

    [$member, $company] = makeActiveCompanyMember();
    assignHrAttendanceRole($member, $company->id, 'member');

    actingAs($member)
        ->get(route('company.hr.attendance.index'))
        ->assertForbidden();
});
