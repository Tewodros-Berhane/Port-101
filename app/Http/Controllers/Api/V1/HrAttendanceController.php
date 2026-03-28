<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\HrAttendancePunchRequest;
use App\Http\Requests\Hr\HrAttendanceRequestStoreRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrAttendanceController extends ApiController
{
    public function records(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrAttendanceRecord::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $status = trim((string) $request->input('status', ''));
        $employeeId = trim((string) $request->input('employee_id', ''));
        $startDate = trim((string) $request->input('start_date', ''));
        $endDate = trim((string) $request->input('end_date', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['attendance_date', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'created_at'],
            defaultSort: 'attendance_date',
            defaultDirection: 'desc',
        );

        [$start, $end] = $this->normalizedDateRange($startDate, $endDate);

        $records = HrAttendanceRecord::query()
            ->with(['employee:id,display_name,employee_number,user_id', 'shift:id,name,code'])
            ->accessibleTo($user)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->when($start && $end, fn ($query) => $query->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()]))
            ->when($start && ! $end, fn ($query) => $query->whereDate('attendance_date', '>=', $start->toDateString()))
            ->when(! $start && $end, fn ($query) => $query->whereDate('attendance_date', '<=', $end->toDateString()))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $records,
            data: collect($records->items())->map(fn (HrAttendanceRecord $record) => $this->mapRecord($record, $user))->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'status' => $status,
                'employee_id' => $employeeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        );
    }

    public function checkIn(HrAttendancePunchRequest $request, HrAttendanceService $attendanceService): JsonResponse
    {
        $this->authorize('create', HrAttendanceRequest::class);

        $record = $attendanceService->checkIn($request->validated(), $request->user());

        return $this->respond($this->mapRecord($record->fresh(['employee:id,display_name,employee_number,user_id', 'shift:id,name,code']), $request->user()));
    }

    public function checkOut(HrAttendancePunchRequest $request, HrAttendanceService $attendanceService): JsonResponse
    {
        $this->authorize('create', HrAttendanceRequest::class);

        $record = $attendanceService->checkOut($request->validated(), $request->user());

        return $this->respond($this->mapRecord($record->fresh(['employee:id,display_name,employee_number,user_id', 'shift:id,name,code']), $request->user()));
    }

    public function storeRequest(
        HrAttendanceRequestStoreRequest $request,
        HrAttendanceService $attendanceService,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('create', HrAttendanceRequest::class);

        $attendanceRequest = $attendanceService->createRequest($request->validated(), $request->user());

        if (($request->validated('action') ?? 'submit') === 'submit') {
            $attendanceRequest = $attendanceService->submit($attendanceRequest, $request->user()->id);
        }

        $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);

        return $this->respond($this->mapCorrectionRequest($attendanceRequest->fresh(['employee:id,display_name,employee_number,user_id', 'approver:id,name']), $request->user()), 201);
    }

    private function mapRecord(HrAttendanceRecord $record, ?User $user): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee_name' => $record->employee?->display_name,
            'employee_number' => $record->employee?->employee_number,
            'attendance_date' => $record->attendance_date?->toDateString(),
            'status' => $record->status,
            'shift_name' => $record->shift?->name,
            'shift_code' => $record->shift?->code,
            'check_in_at' => $record->check_in_at?->toIso8601String(),
            'check_out_at' => $record->check_out_at?->toIso8601String(),
            'worked_minutes' => (int) $record->worked_minutes,
            'late_minutes' => (int) $record->late_minutes,
            'overtime_minutes' => (int) $record->overtime_minutes,
            'approval_status' => $record->approval_status,
            'source_summary' => $record->source_summary,
            'can_view' => $user?->can('view', $record) ?? false,
        ];
    }

    private function mapCorrectionRequest(HrAttendanceRequest $attendanceRequest, ?User $user): array
    {
        return [
            'id' => $attendanceRequest->id,
            'request_number' => $attendanceRequest->request_number,
            'status' => $attendanceRequest->status,
            'employee_id' => $attendanceRequest->employee_id,
            'employee_name' => $attendanceRequest->employee?->display_name,
            'employee_number' => $attendanceRequest->employee?->employee_number,
            'approver_user_id' => $attendanceRequest->approver_user_id,
            'approver_name' => $attendanceRequest->approver?->name,
            'from_date' => $attendanceRequest->from_date?->toDateString(),
            'to_date' => $attendanceRequest->to_date?->toDateString(),
            'requested_status' => $attendanceRequest->requested_status,
            'requested_check_in_at' => $attendanceRequest->requested_check_in_at?->format('H:i:s'),
            'requested_check_out_at' => $attendanceRequest->requested_check_out_at?->format('H:i:s'),
            'reason' => $attendanceRequest->reason,
            'submitted_at' => $attendanceRequest->submitted_at?->toIso8601String(),
            'approved_at' => $attendanceRequest->approved_at?->toIso8601String(),
            'rejected_at' => $attendanceRequest->rejected_at?->toIso8601String(),
            'can_approve' => $user?->can('approve', $attendanceRequest) ?? false,
            'can_reject' => $user?->can('reject', $attendanceRequest) ?? false,
        ];
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function normalizedDateRange(?string $startDate, ?string $endDate): array
    {
        $start = null;
        $end = null;

        if (is_string($startDate) && trim($startDate) !== '') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', trim($startDate))->startOfDay();
            } catch (\Throwable) {
                $start = null;
            }
        }

        if (is_string($endDate) && trim($endDate) !== '') {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d', trim($endDate))->endOfDay();
            } catch (\Throwable) {
                $end = null;
            }
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }
}
