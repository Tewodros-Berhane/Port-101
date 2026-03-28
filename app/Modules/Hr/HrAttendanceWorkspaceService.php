<?php

namespace App\Modules\Hr;

use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrShift;
use App\Modules\Hr\Models\HrShiftAssignment;

class HrAttendanceWorkspaceService
{
    public function summary(User $user): array
    {
        $today = now()->toDateString();
        $recordsQuery = HrAttendanceRecord::query()->accessibleTo($user);
        $requestsQuery = HrAttendanceRequest::query()->accessibleTo($user);
        $assignmentsQuery = HrShiftAssignment::query()->accessibleTo($user);

        return [
            'records_today' => (clone $recordsQuery)
                ->whereDate('attendance_date', $today)
                ->count(),
            'present_today' => (clone $recordsQuery)
                ->whereDate('attendance_date', $today)
                ->where('status', HrAttendanceRecord::STATUS_PRESENT)
                ->count(),
            'missing_today' => (clone $recordsQuery)
                ->whereDate('attendance_date', $today)
                ->where('status', HrAttendanceRecord::STATUS_MISSING)
                ->count(),
            'late_today' => (clone $recordsQuery)
                ->whereDate('attendance_date', $today)
                ->where('late_minutes', '>', 0)
                ->count(),
            'open_corrections' => (clone $requestsQuery)
                ->where('status', HrAttendanceRequest::STATUS_SUBMITTED)
                ->count(),
            'pending_my_approvals' => (clone $requestsQuery)
                ->where('status', HrAttendanceRequest::STATUS_SUBMITTED)
                ->where('approver_user_id', $user->id)
                ->count(),
            'active_shift_assignments' => (clone $assignmentsQuery)
                ->activeOn($today)
                ->count(),
        ];
    }

    public function employeeOptions(User $user): array
    {
        return HrEmployee::query()
            ->with('user:id,name')
            ->accessibleTo($user)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'employee_number', 'user_id'])
            ->map(fn (HrEmployee $employee) => [
                'id' => $employee->id,
                'name' => $employee->display_name,
                'employee_number' => $employee->employee_number,
                'linked_user_name' => $employee->user?->name,
            ])
            ->values()
            ->all();
    }

    public function shiftOptions(): array
    {
        return HrShift::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'start_time', 'end_time'])
            ->map(fn (HrShift $shift) => [
                'id' => $shift->id,
                'name' => $shift->name,
                'code' => $shift->code,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
            ])
            ->values()
            ->all();
    }

    public function linkedEmployeeId(User $user): ?string
    {
        return HrEmployee::query()
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->value('id');
    }
}
