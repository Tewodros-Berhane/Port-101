<?php

namespace App\Modules\Hr;

use App\Models\User;
use App\Modules\Hr\Models\HrAttendanceCheckin;
use App\Modules\Hr\Models\HrAttendanceRecord;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrShift;
use App\Modules\Hr\Models\HrShiftAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrAttendanceService
{
    public function __construct(
        private readonly HrAttendanceNotificationService $notificationService,
    ) {}

    public function createShift(array $attributes, User $actor): HrShift
    {
        return HrShift::create([
            ...$attributes,
            'company_id' => $actor->current_company_id,
            'code' => $this->resolveCode(HrShift::class, (string) $actor->current_company_id, $attributes['code'] ?? null, $attributes['name'] ?? null),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updateShift(HrShift $shift, array $attributes, User $actor): HrShift
    {
        $shift->update([
            ...$attributes,
            'code' => $this->resolveCode(HrShift::class, (string) $shift->company_id, $attributes['code'] ?? null, $attributes['name'] ?? null, (string) $shift->code),
            'updated_by' => $actor->id,
        ]);

        return $shift->fresh() ?? $shift;
    }

    public function createAssignment(array $attributes, User $actor): HrShiftAssignment
    {
        return DB::transaction(function () use ($attributes, $actor): HrShiftAssignment {
            $employee = $this->resolveTargetEmployee($actor, (string) $attributes['employee_id']);
            $this->ensureAssignmentWindowIsValid($attributes['from_date'], $attributes['to_date'] ?? null);
            $this->ensureNoAssignmentOverlap(
                companyId: (string) $actor->current_company_id,
                employeeId: (string) $employee->id,
                fromDate: (string) $attributes['from_date'],
                toDate: $attributes['to_date'] ?? null,
            );

            return HrShiftAssignment::create([
                ...$attributes,
                'company_id' => $actor->current_company_id,
                'employee_id' => $employee->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function updateAssignment(HrShiftAssignment $assignment, array $attributes, User $actor): HrShiftAssignment
    {
        return DB::transaction(function () use ($assignment, $attributes, $actor): HrShiftAssignment {
            $employee = $this->resolveTargetEmployee($actor, (string) ($attributes['employee_id'] ?? $assignment->employee_id));
            $fromDate = (string) ($attributes['from_date'] ?? $assignment->from_date?->toDateString());
            $toDate = $attributes['to_date'] ?? $assignment->to_date?->toDateString();

            $this->ensureAssignmentWindowIsValid($fromDate, $toDate);
            $this->ensureNoAssignmentOverlap(
                companyId: (string) $assignment->company_id,
                employeeId: (string) $employee->id,
                fromDate: $fromDate,
                toDate: $toDate,
                ignoreAssignmentId: (string) $assignment->id,
            );

            $assignment->update([
                ...$attributes,
                'employee_id' => $employee->id,
                'updated_by' => $actor->id,
            ]);

            return $assignment->fresh() ?? $assignment;
        });
    }

    public function checkIn(array $attributes, User $actor): HrAttendanceRecord
    {
        return DB::transaction(function () use ($attributes, $actor): HrAttendanceRecord {
            $employee = $this->resolveTargetEmployee($actor, $attributes['employee_id'] ?? null);
            $recordedAt = $this->resolveRecordedAt($attributes['recorded_at'] ?? null, $employee);
            $attendanceDate = $recordedAt->toDateString();

            $existingRecord = HrAttendanceRecord::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->lockForUpdate()
                ->first();

            if ($existingRecord?->check_in_at) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'This employee is already checked in for the selected attendance date.',
                ]);
            }

            HrAttendanceCheckin::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'recorded_at' => $recordedAt,
                'log_type' => HrAttendanceCheckin::TYPE_IN,
                'source' => (string) ($attributes['source'] ?? HrAttendanceCheckin::SOURCE_WEB),
                'location_data' => $attributes['location_data'] ?? null,
                'device_reference' => $attributes['device_reference'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            return $this->refreshAttendanceRecord($employee, $attendanceDate, $actor->id);
        });
    }

    public function checkOut(array $attributes, User $actor): HrAttendanceRecord
    {
        return DB::transaction(function () use ($attributes, $actor): HrAttendanceRecord {
            $employee = $this->resolveTargetEmployee($actor, $attributes['employee_id'] ?? null);
            $recordedAt = $this->resolveRecordedAt($attributes['recorded_at'] ?? null, $employee);
            $attendanceDate = $recordedAt->toDateString();

            $existingRecord = HrAttendanceRecord::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->whereDate('attendance_date', $attendanceDate)
                ->lockForUpdate()
                ->first();

            if (! $existingRecord?->check_in_at) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'Check-in is required before check-out can be recorded.',
                ]);
            }

            if ($existingRecord->check_out_at) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'This employee is already checked out for the selected attendance date.',
                ]);
            }

            if ($recordedAt->lt($existingRecord->check_in_at)) {
                throw ValidationException::withMessages([
                    'recorded_at' => 'Check-out time cannot be earlier than the recorded check-in time.',
                ]);
            }

            HrAttendanceCheckin::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'recorded_at' => $recordedAt,
                'log_type' => HrAttendanceCheckin::TYPE_OUT,
                'source' => (string) ($attributes['source'] ?? HrAttendanceCheckin::SOURCE_WEB),
                'location_data' => $attributes['location_data'] ?? null,
                'device_reference' => $attributes['device_reference'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            return $this->refreshAttendanceRecord($employee, $attendanceDate, $actor->id);
        });
    }

    public function createRequest(array $attributes, User $actor): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attributes, $actor): HrAttendanceRequest {
            $employee = $this->resolveTargetEmployee($actor, $attributes['employee_id'] ?? null);
            $requestData = $this->normalizeAttendanceRequestPayload(
                employee: $employee,
                attributes: $attributes,
                companyId: (string) $actor->current_company_id,
                actorId: $actor->id,
            );

            return HrAttendanceRequest::create($requestData);
        });
    }

    public function updateRequest(HrAttendanceRequest $attendanceRequest, array $attributes, User $actor): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attendanceRequest, $attributes, $actor): HrAttendanceRequest {
            $employee = $this->resolveTargetEmployee($actor, $attributes['employee_id'] ?? $attendanceRequest->employee_id);
            $requestData = $this->normalizeAttendanceRequestPayload(
                employee: $employee,
                attributes: $attributes,
                companyId: (string) $attendanceRequest->company_id,
                actorId: $actor->id,
                currentRequestNumber: (string) $attendanceRequest->request_number,
            );

            $attendanceRequest->update([
                ...$requestData,
                'status' => $attendanceRequest->status,
                'submitted_at' => $attendanceRequest->submitted_at,
                'approved_at' => $attendanceRequest->approved_at,
                'rejected_at' => $attendanceRequest->rejected_at,
                'cancelled_at' => $attendanceRequest->cancelled_at,
            ]);

            return $attendanceRequest->fresh() ?? $attendanceRequest;
        });
    }

    public function submit(HrAttendanceRequest $attendanceRequest, ?string $actorId = null): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attendanceRequest, $actorId): HrAttendanceRequest {
            $attendanceRequest = HrAttendanceRequest::query()
                ->with(['employee.department'])
                ->lockForUpdate()
                ->findOrFail($attendanceRequest->id);

            if (! in_array($attendanceRequest->status, [HrAttendanceRequest::STATUS_DRAFT, HrAttendanceRequest::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'attendance_request' => 'Only draft or rejected attendance corrections can be submitted.',
                ]);
            }

            $approverUserId = $this->resolveAttendanceApprover($attendanceRequest->employee);

            if (! $approverUserId) {
                throw ValidationException::withMessages([
                    'attendance_request' => 'No attendance approver is configured for this employee.',
                ]);
            }

            $this->ensureNoSubmittedOverlap($attendanceRequest);

            $attendanceRequest->update([
                'approver_user_id' => $approverUserId,
                'status' => HrAttendanceRequest::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'cancelled_by_user_id' => null,
                'cancelled_at' => null,
                'decision_notes' => null,
                'updated_by' => $actorId,
            ]);

            $attendanceRequest->loadMissing('employee');
            $this->notificationService->notifyCorrectionSubmitted($attendanceRequest, $actorId);

            return $attendanceRequest->fresh() ?? $attendanceRequest;
        });
    }

    public function approve(HrAttendanceRequest $attendanceRequest, ?string $actorId = null): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attendanceRequest, $actorId): HrAttendanceRequest {
            $attendanceRequest = HrAttendanceRequest::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($attendanceRequest->id);

            if ($attendanceRequest->status !== HrAttendanceRequest::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'attendance_request' => 'Only submitted attendance corrections can be approved.',
                ]);
            }

            $this->applyAttendanceRequest($attendanceRequest, $actorId);

            $attendanceRequest->update([
                'status' => HrAttendanceRequest::STATUS_APPROVED,
                'approved_by_user_id' => $actorId,
                'approved_at' => now(),
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'cancelled_by_user_id' => null,
                'cancelled_at' => null,
                'decision_notes' => null,
                'updated_by' => $actorId,
            ]);

            $attendanceRequest->loadMissing('employee');
            $this->notificationService->notifyCorrectionDecision($attendanceRequest, 'approved', $actorId);

            return $attendanceRequest->fresh() ?? $attendanceRequest;
        });
    }

    public function reject(HrAttendanceRequest $attendanceRequest, ?string $reason = null, ?string $actorId = null): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attendanceRequest, $reason, $actorId): HrAttendanceRequest {
            $attendanceRequest = HrAttendanceRequest::query()
                ->with('employee')
                ->lockForUpdate()
                ->findOrFail($attendanceRequest->id);

            if ($attendanceRequest->status !== HrAttendanceRequest::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'attendance_request' => 'Only submitted attendance corrections can be rejected.',
                ]);
            }

            $attendanceRequest->update([
                'status' => HrAttendanceRequest::STATUS_REJECTED,
                'approved_by_user_id' => null,
                'approved_at' => null,
                'rejected_by_user_id' => $actorId,
                'rejected_at' => now(),
                'decision_notes' => $reason,
                'updated_by' => $actorId,
            ]);

            $attendanceRequest->loadMissing('employee');
            $this->notificationService->notifyCorrectionDecision($attendanceRequest, 'rejected', $actorId);

            return $attendanceRequest->fresh() ?? $attendanceRequest;
        });
    }

    public function cancel(HrAttendanceRequest $attendanceRequest, ?string $actorId = null): HrAttendanceRequest
    {
        return DB::transaction(function () use ($attendanceRequest, $actorId): HrAttendanceRequest {
            $attendanceRequest = HrAttendanceRequest::query()
                ->lockForUpdate()
                ->findOrFail($attendanceRequest->id);

            if (! in_array($attendanceRequest->status, [
                HrAttendanceRequest::STATUS_DRAFT,
                HrAttendanceRequest::STATUS_SUBMITTED,
                HrAttendanceRequest::STATUS_REJECTED,
            ], true)) {
                throw ValidationException::withMessages([
                    'attendance_request' => 'Approved attendance corrections cannot be cancelled. Submit a new correction instead.',
                ]);
            }

            $attendanceRequest->update([
                'status' => HrAttendanceRequest::STATUS_CANCELLED,
                'cancelled_by_user_id' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $attendanceRequest->fresh() ?? $attendanceRequest;
        });
    }

    public function refreshAttendanceRecord(HrEmployee $employee, string $attendanceDate, ?string $actorId = null): HrAttendanceRecord
    {
        $employee->loadMissing('department');

        $recordDate = Carbon::parse($attendanceDate)->toDateString();
        $shift = $this->resolveShiftForEmployee($employee, $recordDate);
        $checkins = HrAttendanceCheckin::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('recorded_at', $recordDate)
            ->orderBy('recorded_at')
            ->get();

        $checkInAt = $checkins->firstWhere('log_type', HrAttendanceCheckin::TYPE_IN)?->recorded_at;
        $checkOutAt = $checkins->reverse()->firstWhere('log_type', HrAttendanceCheckin::TYPE_OUT)?->recorded_at;

        if ($checkInAt && $checkOutAt && $checkOutAt->lt($checkInAt)) {
            $checkOutAt = null;
        }

        $workedMinutes = $this->calculateWorkedMinutes($checkInAt, $checkOutAt);
        $lateMinutes = $this->calculateLateMinutes($recordDate, $shift, $checkInAt);
        $overtimeMinutes = $this->calculateOvertimeMinutes($recordDate, $shift, $checkOutAt);
        $approvedLeaveState = $this->approvedLeaveState($employee, $recordDate);
        $status = $this->deriveAttendanceStatus($checkInAt, $checkOutAt, $approvedLeaveState);

        $record = HrAttendanceRecord::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', $recordDate)
            ->first();

        $payload = [
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'shift_id' => $shift?->id,
            'attendance_date' => $recordDate,
            'status' => $status,
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'late_minutes' => $lateMinutes,
            'approval_status' => HrAttendanceRecord::APPROVAL_NOT_REQUIRED,
            'approved_by_user_id' => null,
            'source_summary' => $this->buildSourceSummary($checkins->count(), $approvedLeaveState),
            'updated_by' => $actorId,
        ];

        if (! $record) {
            return HrAttendanceRecord::create([
                ...$payload,
                'created_by' => $actorId,
            ]);
        }

        $record->update($payload);

        return $record->fresh() ?? $record;
    }

    private function normalizeAttendanceRequestPayload(
        HrEmployee $employee,
        array $attributes,
        string $companyId,
        ?string $actorId,
        ?string $currentRequestNumber = null,
    ): array {
        $fromDate = Carbon::parse((string) $attributes['from_date'])->toDateString();
        $toDate = Carbon::parse((string) $attributes['to_date'])->toDateString();

        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'to_date' => 'Attendance correction end date must be on or after the start date.',
            ]);
        }

        $requestedStatus = (string) $attributes['requested_status'];
        $requestedCheckInAt = $this->normalizeRequestedTimestamp($attributes['requested_check_in_at'] ?? null, $fromDate);
        $requestedCheckOutAt = $this->normalizeRequestedTimestamp($attributes['requested_check_out_at'] ?? null, $fromDate);

        if ($requestedCheckInAt && $requestedCheckOutAt && $requestedCheckOutAt->lt($requestedCheckInAt)) {
            throw ValidationException::withMessages([
                'requested_check_out_at' => 'Requested check-out time cannot be earlier than requested check-in time.',
            ]);
        }

        if ($fromDate !== $toDate && ($requestedCheckInAt || $requestedCheckOutAt)) {
            throw ValidationException::withMessages([
                'requested_check_in_at' => 'Time overrides are only supported for single-day attendance corrections.',
            ]);
        }

        if (in_array($requestedStatus, [HrAttendanceRecord::STATUS_PRESENT, HrAttendanceRecord::STATUS_HALF_DAY], true) && ! $requestedCheckInAt) {
            throw ValidationException::withMessages([
                'requested_check_in_at' => 'Check-in time is required when correcting to present or half-day.',
            ]);
        }

        if ($requestedCheckInAt && ! $requestedCheckOutAt && $requestedStatus === HrAttendanceRecord::STATUS_PRESENT) {
            throw ValidationException::withMessages([
                'requested_check_out_at' => 'Check-out time is required when correcting to present.',
            ]);
        }

        return [
            ...Arr::only($attributes, ['reason']),
            'company_id' => $companyId,
            'employee_id' => $employee->id,
            'requested_by_user_id' => $actorId,
            'approver_user_id' => $this->resolveAttendanceApprover($employee),
            'request_number' => $this->resolveRequestNumber($companyId, $currentRequestNumber),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'requested_status' => $requestedStatus,
            'requested_check_in_at' => $requestedCheckInAt,
            'requested_check_out_at' => $requestedCheckOutAt,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    private function resolveTargetEmployee(User $actor, ?string $employeeId = null): HrEmployee
    {
        $employee = HrEmployee::query()
            ->where('company_id', $actor->current_company_id)
            ->when($employeeId, fn ($query) => $query->whereKey($employeeId), fn ($query) => $query->where('user_id', $actor->id))
            ->first();

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'A valid employee record is required for attendance activity.',
            ]);
        }

        if ((string) $employee->user_id === (string) $actor->id) {
            return $employee;
        }

        if (
            $actor->hasPermission('hr.attendance.manage')
            && HrEmployee::query()->accessibleTo($actor)->whereKey($employee->id)->exists()
        ) {
            return $employee;
        }

        throw ValidationException::withMessages([
            'employee_id' => 'You cannot manage attendance for the selected employee.',
        ]);
    }

    private function resolveRecordedAt(mixed $value, HrEmployee $employee): Carbon
    {
        if (! filled($value)) {
            return now($employee->timezone ?: 'UTC');
        }

        return Carbon::parse((string) $value, $employee->timezone ?: 'UTC');
    }

    private function normalizeRequestedTimestamp(mixed $value, string $attendanceDate): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        $stringValue = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $stringValue) === 1) {
            return Carbon::parse($attendanceDate.' '.$stringValue.':00');
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $stringValue) === 1) {
            return Carbon::parse($attendanceDate.' '.$stringValue);
        }

        return Carbon::parse($stringValue);
    }

    private function ensureAssignmentWindowIsValid(string $fromDate, ?string $toDate): void
    {
        if ($toDate !== null && Carbon::parse($fromDate)->gt(Carbon::parse($toDate))) {
            throw ValidationException::withMessages([
                'to_date' => 'Shift assignment end date must be on or after the start date.',
            ]);
        }
    }

    private function ensureNoAssignmentOverlap(
        string $companyId,
        string $employeeId,
        string $fromDate,
        ?string $toDate,
        ?string $ignoreAssignmentId = null,
    ): void {
        $overlap = HrShiftAssignment::query()
            ->where('company_id', $companyId)
            ->where('employee_id', $employeeId)
            ->when($ignoreAssignmentId, fn ($query, $assignmentId) => $query->whereKeyNot($assignmentId))
            ->whereDate('from_date', '<=', $toDate ?? '9999-12-31')
            ->where(function ($query) use ($fromDate): void {
                $query
                    ->whereNull('to_date')
                    ->orWhereDate('to_date', '>=', $fromDate);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => 'This employee already has an overlapping shift assignment in the selected period.',
            ]);
        }
    }

    private function ensureNoSubmittedOverlap(HrAttendanceRequest $attendanceRequest): void
    {
        $overlap = HrAttendanceRequest::query()
            ->where('company_id', $attendanceRequest->company_id)
            ->where('employee_id', $attendanceRequest->employee_id)
            ->where('status', HrAttendanceRequest::STATUS_SUBMITTED)
            ->whereKeyNot($attendanceRequest->id)
            ->whereDate('from_date', '<=', $attendanceRequest->to_date)
            ->whereDate('to_date', '>=', $attendanceRequest->from_date)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'from_date' => 'This employee already has a submitted attendance correction covering part of the selected date range.',
            ]);
        }
    }

    private function resolveAttendanceApprover(?HrEmployee $employee): ?string
    {
        if (! $employee) {
            return null;
        }

        if ($employee->attendance_approver_user_id) {
            return (string) $employee->attendance_approver_user_id;
        }

        $employee->loadMissing('department');

        if ($employee->department?->attendance_approver_user_id) {
            return (string) $employee->department->attendance_approver_user_id;
        }

        if (! $employee->manager_employee_id) {
            return null;
        }

        return HrEmployee::query()
            ->whereKey($employee->manager_employee_id)
            ->value('user_id');
    }

    private function resolveShiftForEmployee(HrEmployee $employee, string $attendanceDate): ?HrShift
    {
        return HrShiftAssignment::query()
            ->with('shift')
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->activeOn($attendanceDate)
            ->latest('from_date')
            ->first()?->shift;
    }

    private function approvedLeaveState(HrEmployee $employee, string $attendanceDate): string
    {
        $leaveRequests = HrLeaveRequest::query()
            ->where('company_id', $employee->company_id)
            ->where('employee_id', $employee->id)
            ->where('status', HrLeaveRequest::STATUS_APPROVED)
            ->whereDate('from_date', '<=', $attendanceDate)
            ->whereDate('to_date', '>=', $attendanceDate)
            ->get(['is_half_day']);

        if ($leaveRequests->isEmpty()) {
            return 'none';
        }

        return $leaveRequests->contains(fn (HrLeaveRequest $leaveRequest) => (bool) $leaveRequest->is_half_day)
            ? HrAttendanceRecord::STATUS_HALF_DAY
            : HrAttendanceRecord::STATUS_ON_LEAVE;
    }

    private function deriveAttendanceStatus(?CarbonInterface $checkInAt, ?CarbonInterface $checkOutAt, string $approvedLeaveState): string
    {
        if ($approvedLeaveState === HrAttendanceRecord::STATUS_ON_LEAVE && ! $checkInAt) {
            return HrAttendanceRecord::STATUS_ON_LEAVE;
        }

        if ($approvedLeaveState === HrAttendanceRecord::STATUS_HALF_DAY) {
            return HrAttendanceRecord::STATUS_HALF_DAY;
        }

        if ($checkInAt && $checkOutAt) {
            return HrAttendanceRecord::STATUS_PRESENT;
        }

        if ($checkInAt) {
            return HrAttendanceRecord::STATUS_MISSING;
        }

        return HrAttendanceRecord::STATUS_MISSING;
    }

    private function calculateWorkedMinutes(?CarbonInterface $checkInAt, ?CarbonInterface $checkOutAt): int
    {
        if (! $checkInAt || ! $checkOutAt || $checkOutAt->lte($checkInAt)) {
            return 0;
        }

        return (int) $checkInAt->diffInMinutes($checkOutAt);
    }

    private function calculateLateMinutes(string $attendanceDate, ?HrShift $shift, ?CarbonInterface $checkInAt): int
    {
        if (! $shift || ! $checkInAt) {
            return 0;
        }

        $shiftStart = Carbon::parse($attendanceDate.' '.$shift->start_time);
        $graceCutoff = $shiftStart->copy()->addMinutes((int) $shift->grace_minutes);

        if ($checkInAt->lte($graceCutoff)) {
            return 0;
        }

        return (int) $graceCutoff->diffInMinutes($checkInAt);
    }

    private function calculateOvertimeMinutes(string $attendanceDate, ?HrShift $shift, ?CarbonInterface $checkOutAt): int
    {
        if (! $shift || ! $checkOutAt) {
            return 0;
        }

        $shiftEnd = Carbon::parse($attendanceDate.' '.$shift->end_time);
        $shiftStart = Carbon::parse($attendanceDate.' '.$shift->start_time);

        if ($shiftEnd->lte($shiftStart)) {
            $shiftEnd->addDay();
        }

        if ($checkOutAt->lte($shiftEnd)) {
            return 0;
        }

        return (int) $shiftEnd->diffInMinutes($checkOutAt);
    }

    private function buildSourceSummary(int $checkinCount, string $approvedLeaveState): string
    {
        $segments = [sprintf('%d raw log%s', $checkinCount, $checkinCount === 1 ? '' : 's')];

        if ($approvedLeaveState !== 'none') {
            $segments[] = 'leave='.$approvedLeaveState;
        }

        return implode('; ', $segments);
    }

    private function applyAttendanceRequest(HrAttendanceRequest $attendanceRequest, ?string $actorId = null): void
    {
        $employee = $attendanceRequest->employee;

        if (! $employee) {
            throw ValidationException::withMessages([
                'attendance_request' => 'Attendance correction employee record is missing.',
            ]);
        }

        $start = Carbon::parse($attendanceRequest->from_date)->startOfDay();
        $end = Carbon::parse($attendanceRequest->to_date)->startOfDay();

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $date = $cursor->toDateString();
            $shift = $this->resolveShiftForEmployee($employee, $date);
            $checkInAt = $attendanceRequest->requested_check_in_at
                ? Carbon::parse($date.' '.$attendanceRequest->requested_check_in_at->format('H:i:s'))
                : null;
            $checkOutAt = $attendanceRequest->requested_check_out_at
                ? Carbon::parse($date.' '.$attendanceRequest->requested_check_out_at->format('H:i:s'))
                : null;

            $status = $attendanceRequest->requested_status;
            $workedMinutes = in_array($status, [HrAttendanceRecord::STATUS_PRESENT, HrAttendanceRecord::STATUS_HALF_DAY], true)
                ? $this->calculateWorkedMinutes($checkInAt, $checkOutAt)
                : 0;
            $lateMinutes = in_array($status, [HrAttendanceRecord::STATUS_PRESENT, HrAttendanceRecord::STATUS_HALF_DAY], true)
                ? $this->calculateLateMinutes($date, $shift, $checkInAt)
                : 0;
            $overtimeMinutes = in_array($status, [HrAttendanceRecord::STATUS_PRESENT, HrAttendanceRecord::STATUS_HALF_DAY], true)
                ? $this->calculateOvertimeMinutes($date, $shift, $checkOutAt)
                : 0;

            $record = HrAttendanceRecord::query()
                ->where('company_id', $attendanceRequest->company_id)
                ->where('employee_id', $attendanceRequest->employee_id)
                ->whereDate('attendance_date', $date)
                ->first();

            $payload = [
                'company_id' => $attendanceRequest->company_id,
                'employee_id' => $attendanceRequest->employee_id,
                'shift_id' => $shift?->id,
                'attendance_date' => $date,
                'status' => $status,
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'worked_minutes' => $workedMinutes,
                'late_minutes' => $lateMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'approval_status' => HrAttendanceRecord::APPROVAL_APPROVED,
                'approved_by_user_id' => $actorId,
                'source_summary' => 'Correction approved from request '.$attendanceRequest->request_number,
                'updated_by' => $actorId,
            ];

            if (! $record) {
                HrAttendanceRecord::create([
                    ...$payload,
                    'created_by' => $actorId,
                ]);

                continue;
            }

            $record->update($payload);
        }
    }

    private function resolveCode(string $modelClass, string $companyId, mixed $proposed, mixed $fallback, ?string $current = null): ?string
    {
        $candidate = Str::upper(Str::limit(trim((string) $proposed), 32, ''));

        if ($candidate === '' && filled($fallback)) {
            $candidate = Str::upper(Str::limit(Str::slug((string) $fallback, ''), 12, ''));
        }

        if ($candidate === '') {
            return $current;
        }

        if ($current && $candidate === $current) {
            return $current;
        }

        $sequence = 1;
        $base = $candidate;

        while ($modelClass::withTrashed()
            ->where('company_id', $companyId)
            ->where('code', $candidate)
            ->exists()) {
            $candidate = Str::limit($base, 28, '').str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        }

        return $candidate;
    }

    private function resolveRequestNumber(string $companyId, ?string $current = null): string
    {
        if ($current) {
            return $current;
        }

        $prefix = 'ATT-';
        $latest = HrAttendanceRequest::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $sequence = $latest ? ((int) Str::afterLast((string) $latest, '-')) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
