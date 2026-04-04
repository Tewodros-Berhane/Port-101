<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrAttendancePunchRequest;
use App\Models\User;
use App\Support\Http\Feedback;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\HrAttendanceWorkspaceService;
use App\Modules\Hr\Models\HrAttendanceCheckin;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrShift;
use App\Modules\Hr\Models\HrShiftAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HrAttendanceController extends Controller
{
    public function index(Request $request, HrAttendanceWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrAttendanceRecord::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:present,absent,on_leave,half_day,missing,holiday'],
            'approval_status' => ['nullable', 'string', 'in:not_required,submitted,approved,rejected'],
            'employee_id' => ['nullable', 'uuid'],
            'attendance_date' => ['nullable', 'date'],
        ]);

        $records = HrAttendanceRecord::query()
            ->with([
                'employee:id,display_name,employee_number',
                'shift:id,name,code,start_time,end_time',
                'approvedBy:id,name',
            ])
            ->accessibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['approval_status'] ?? null, fn ($query, $approvalStatus) => $query->where('approval_status', $approvalStatus))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->when($filters['attendance_date'] ?? null, fn ($query, $attendanceDate) => $query->whereDate('attendance_date', $attendanceDate))
            ->orderByDesc('attendance_date')
            ->paginate(20)
            ->withQueryString();

        $correctionRequests = HrAttendanceRequest::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'approver:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'cancelledBy:id,name',
            ])
            ->accessibleTo($user)
            ->latest('created_at')
            ->limit(12)
            ->get();

        $recentCheckins = HrAttendanceCheckin::query()
            ->with('employee:id,display_name,employee_number')
            ->accessibleTo($user)
            ->latest('recorded_at')
            ->limit(12)
            ->get();

        $assignments = HrShiftAssignment::query()
            ->with(['employee:id,display_name,employee_number', 'shift:id,name,code,start_time,end_time'])
            ->accessibleTo($user)
            ->latest('from_date')
            ->limit(12)
            ->get();

        $linkedEmployeeId = $workspaceService->linkedEmployeeId($user);
        $openRecord = $linkedEmployeeId
            ? HrAttendanceRecord::query()
                ->where('company_id', $user->current_company_id)
                ->where('employee_id', $linkedEmployeeId)
                ->whereDate('attendance_date', now()->toDateString())
                ->whereNotNull('check_in_at')
                ->whereNull('check_out_at')
                ->first()
            : null;

        return Inertia::render('hr/attendance/index', [
            'summary' => $workspaceService->summary($user),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'approval_status' => $filters['approval_status'] ?? '',
                'employee_id' => $filters['employee_id'] ?? '',
                'attendance_date' => $filters['attendance_date'] ?? '',
            ],
            'statuses' => HrAttendanceRecord::STATUSES,
            'approvalStatuses' => HrAttendanceRecord::APPROVAL_STATUSES,
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'shiftOptions' => $workspaceService->shiftOptions(),
            'linkedEmployeeId' => $linkedEmployeeId,
            'openAttendanceRecordId' => $openRecord?->id,
            'todayIso' => now()->format('Y-m-d\TH:i'),
            'records' => $records->through(fn (HrAttendanceRecord $attendanceRecord) => [
                'id' => $attendanceRecord->id,
                'attendance_date' => $attendanceRecord->attendance_date?->toDateString(),
                'status' => $attendanceRecord->status,
                'approval_status' => $attendanceRecord->approval_status,
                'employee_name' => $attendanceRecord->employee?->display_name,
                'employee_number' => $attendanceRecord->employee?->employee_number,
                'shift_name' => $attendanceRecord->shift?->name,
                'check_in_at' => $attendanceRecord->check_in_at?->format('Y-m-d H:i'),
                'check_out_at' => $attendanceRecord->check_out_at?->format('Y-m-d H:i'),
                'worked_minutes' => (int) $attendanceRecord->worked_minutes,
                'late_minutes' => (int) $attendanceRecord->late_minutes,
                'overtime_minutes' => (int) $attendanceRecord->overtime_minutes,
                'approved_by_name' => $attendanceRecord->approvedBy?->name,
                'source_summary' => $attendanceRecord->source_summary,
            ]),
            'correctionRequests' => $correctionRequests->map(fn (HrAttendanceRequest $attendanceRequest) => [
                'id' => $attendanceRequest->id,
                'request_number' => $attendanceRequest->request_number,
                'status' => $attendanceRequest->status,
                'employee_name' => $attendanceRequest->employee?->display_name,
                'employee_number' => $attendanceRequest->employee?->employee_number,
                'approver_name' => $attendanceRequest->approver?->name,
                'requested_status' => $attendanceRequest->requested_status,
                'from_date' => $attendanceRequest->from_date?->toDateString(),
                'to_date' => $attendanceRequest->to_date?->toDateString(),
                'requested_check_in_at' => $attendanceRequest->requested_check_in_at?->format('Y-m-d H:i'),
                'requested_check_out_at' => $attendanceRequest->requested_check_out_at?->format('Y-m-d H:i'),
                'reason' => $attendanceRequest->reason,
                'decision_notes' => $attendanceRequest->decision_notes,
                'can_edit' => $user->can('update', $attendanceRequest),
                'can_submit' => $user->can('submit', $attendanceRequest),
                'can_approve' => $user->can('approve', $attendanceRequest),
                'can_reject' => $user->can('reject', $attendanceRequest),
                'can_cancel' => $user->can('cancel', $attendanceRequest),
            ])->values()->all(),
            'recentCheckins' => $recentCheckins->map(fn (HrAttendanceCheckin $checkin) => [
                'id' => $checkin->id,
                'employee_name' => $checkin->employee?->display_name,
                'employee_number' => $checkin->employee?->employee_number,
                'log_type' => $checkin->log_type,
                'source' => $checkin->source,
                'recorded_at' => $checkin->recorded_at?->format('Y-m-d H:i'),
                'device_reference' => $checkin->device_reference,
            ])->values()->all(),
            'assignments' => $assignments->map(fn (HrShiftAssignment $assignment) => [
                'id' => $assignment->id,
                'employee_name' => $assignment->employee?->display_name,
                'employee_number' => $assignment->employee?->employee_number,
                'shift_name' => $assignment->shift?->name,
                'shift_window' => trim(substr((string) $assignment->shift?->start_time, 0, 5).' - '.substr((string) $assignment->shift?->end_time, 0, 5)),
                'from_date' => $assignment->from_date?->toDateString(),
                'to_date' => $assignment->to_date?->toDateString(),
                'can_edit' => $user->can('update', $assignment),
            ])->values()->all(),
            'abilities' => [
                'can_record_attendance' => $user->can('create', HrAttendanceRecord::class),
                'can_create_request' => $user->can('create', HrAttendanceRequest::class),
                'can_manage_attendance' => $user->can('create', HrShift::class),
            ],
        ]);
    }

    public function checkIn(HrAttendancePunchRequest $request, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('create', HrAttendanceRecord::class);

        $attendanceService->checkIn($request->validated(), $request->user());

        return back()->with('success', Feedback::flash($request, 'Check-in recorded.'));
    }

    public function checkOut(HrAttendancePunchRequest $request, HrAttendanceService $attendanceService): RedirectResponse
    {
        $this->authorize('create', HrAttendanceRecord::class);

        $attendanceService->checkOut($request->validated(), $request->user());

        return back()->with('success', Feedback::flash($request, 'Check-out recorded.'));
    }
}
