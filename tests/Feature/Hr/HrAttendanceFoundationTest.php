<?php

use App\Modules\Hr\Models\HrAttendanceCheckin;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrShift;
use App\Modules\Hr\Models\HrShiftAssignment;
use Illuminate\Support\Facades\Schema;

test('hr attendance tables exist and attendance relations persist', function () {
    foreach ([
        'hr_shifts',
        'hr_shift_assignments',
        'hr_attendance_records',
        'hr_attendance_checkins',
        'hr_attendance_requests',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    [$user, $company] = makeActiveCompanyMember();

    $employee = HrEmployee::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'employee_number' => 'EMP-ATT-001',
        'employment_status' => HrEmployee::STATUS_ACTIVE,
        'employment_type' => HrEmployee::TYPE_FULL_TIME,
        'first_name' => 'Amina',
        'last_name' => 'Timekeeper',
        'display_name' => 'Amina Timekeeper',
        'hire_date' => '2026-01-01',
        'timezone' => 'UTC',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $shift = HrShift::create([
        'company_id' => $company->id,
        'name' => 'Morning Shift',
        'code' => 'MS1',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'grace_minutes' => 10,
        'auto_attendance_enabled' => false,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $assignment = HrShiftAssignment::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'from_date' => '2026-03-01',
        'to_date' => null,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $record = HrAttendanceRecord::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'shift_id' => $shift->id,
        'attendance_date' => '2026-03-28',
        'status' => HrAttendanceRecord::STATUS_PRESENT,
        'check_in_at' => '2026-03-28 09:05:00',
        'check_out_at' => '2026-03-28 17:02:00',
        'worked_minutes' => 477,
        'overtime_minutes' => 2,
        'late_minutes' => 0,
        'approval_status' => HrAttendanceRecord::APPROVAL_NOT_REQUIRED,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $checkin = HrAttendanceCheckin::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'recorded_at' => '2026-03-28 09:05:00',
        'log_type' => HrAttendanceCheckin::TYPE_IN,
        'source' => HrAttendanceCheckin::SOURCE_WEB,
        'created_by_user_id' => $user->id,
    ]);

    $attendanceRequest = HrAttendanceRequest::create([
        'company_id' => $company->id,
        'employee_id' => $employee->id,
        'requested_by_user_id' => $user->id,
        'request_number' => 'ATT-0001',
        'status' => HrAttendanceRequest::STATUS_DRAFT,
        'from_date' => '2026-03-28',
        'to_date' => '2026-03-28',
        'requested_status' => HrAttendanceRecord::STATUS_PRESENT,
        'requested_check_in_at' => '2026-03-28 09:00:00',
        'requested_check_out_at' => '2026-03-28 17:00:00',
        'reason' => 'Clock-in delay on browser startup.',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($employee->shiftAssignments()->count())->toBe(1);
    expect($employee->attendanceRecords()->count())->toBe(1);
    expect($employee->attendanceCheckins()->count())->toBe(1);
    expect($employee->attendanceRequests()->count())->toBe(1);
    expect($assignment->shift?->is($shift))->toBeTrue();
    expect($record->employee?->is($employee))->toBeTrue();
    expect($checkin->employee?->is($employee))->toBeTrue();
    expect($attendanceRequest->employee?->is($employee))->toBeTrue();
});
