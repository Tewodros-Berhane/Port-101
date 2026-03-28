<?php

namespace App\Modules\Hr;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeaveRequest;

class HrLeaveWorkspaceService
{
    public function summary(User $user): array
    {
        $requestQuery = HrLeaveRequest::query()->accessibleTo($user);
        $allocationQuery = HrLeaveAllocation::query()->accessibleTo($user);

        return [
            'open_requests' => (clone $requestQuery)
                ->where('status', HrLeaveRequest::STATUS_SUBMITTED)
                ->count(),
            'pending_my_approvals' => (clone $requestQuery)
                ->where('status', HrLeaveRequest::STATUS_SUBMITTED)
                ->where('approver_user_id', $user->id)
                ->count(),
            'approved_30d' => (clone $requestQuery)
                ->where('status', HrLeaveRequest::STATUS_APPROVED)
                ->where('approved_at', '>=', now()->subDays(30))
                ->count(),
            'allocations' => (clone $allocationQuery)->count(),
            'available_days' => round((float) (clone $allocationQuery)->sum('balance_amount'), 2),
            'booked_days_30d' => round((float) (clone $requestQuery)
                ->whereIn('status', [HrLeaveRequest::STATUS_SUBMITTED, HrLeaveRequest::STATUS_APPROVED])
                ->where('from_date', '>=', now()->subDays(30)->toDateString())
                ->sum('duration_amount'), 2),
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
}
