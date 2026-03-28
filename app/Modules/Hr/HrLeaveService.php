<?php

namespace App\Modules\Hr;

use App\Models\User;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HrLeaveService
{
    public function __construct(
        private readonly HrLeaveNotificationService $notificationService,
    ) {}

    public function createType(array $attributes, User $actor): HrLeaveType
    {
        return HrLeaveType::create([
            ...$attributes,
            'company_id' => $actor->current_company_id,
            'code' => $this->resolveCode(HrLeaveType::class, (string) $actor->current_company_id, $attributes['code'] ?? null, $attributes['name'] ?? null),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updateType(HrLeaveType $leaveType, array $attributes, User $actor): HrLeaveType
    {
        $leaveType->update([
            ...$attributes,
            'code' => $this->resolveCode(HrLeaveType::class, (string) $leaveType->company_id, $attributes['code'] ?? null, $attributes['name'] ?? null, (string) $leaveType->code),
            'updated_by' => $actor->id,
        ]);

        return $leaveType->fresh() ?? $leaveType;
    }

    public function createPeriod(array $attributes, User $actor): HrLeavePeriod
    {
        $this->ensurePeriodDatesAreValid($attributes['start_date'], $attributes['end_date']);

        return HrLeavePeriod::create([
            ...$attributes,
            'company_id' => $actor->current_company_id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    public function updatePeriod(HrLeavePeriod $leavePeriod, array $attributes, User $actor): HrLeavePeriod
    {
        $this->ensurePeriodDatesAreValid($attributes['start_date'], $attributes['end_date']);

        $leavePeriod->update([
            ...$attributes,
            'updated_by' => $actor->id,
        ]);

        return $leavePeriod->fresh() ?? $leavePeriod;
    }

    public function createAllocation(array $attributes, User $actor): HrLeaveAllocation
    {
        return DB::transaction(function () use ($attributes, $actor): HrLeaveAllocation {
            $carryForwardAmount = round((float) ($attributes['carry_forward_amount'] ?? 0), 2);
            $allocatedAmount = round((float) ($attributes['allocated_amount'] ?? 0), 2);
            $usedAmount = round((float) ($attributes['used_amount'] ?? 0), 2);
            $balanceAmount = round($allocatedAmount + $carryForwardAmount - $usedAmount, 2);

            if ($balanceAmount < 0) {
                throw ValidationException::withMessages([
                    'allocated_amount' => 'Allocated leave cannot be less than used leave.',
                ]);
            }

            return HrLeaveAllocation::create([
                ...$attributes,
                'company_id' => $actor->current_company_id,
                'allocated_amount' => $allocatedAmount,
                'used_amount' => $usedAmount,
                'balance_amount' => $balanceAmount,
                'carry_forward_amount' => $carryForwardAmount,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function updateAllocation(HrLeaveAllocation $allocation, array $attributes, User $actor): HrLeaveAllocation
    {
        return DB::transaction(function () use ($allocation, $attributes, $actor): HrLeaveAllocation {
            $carryForwardAmount = round((float) ($attributes['carry_forward_amount'] ?? $allocation->carry_forward_amount ?? 0), 2);
            $allocatedAmount = round((float) ($attributes['allocated_amount'] ?? $allocation->allocated_amount ?? 0), 2);
            $usedAmount = round((float) ($attributes['used_amount'] ?? $allocation->used_amount ?? 0), 2);
            $balanceAmount = round($allocatedAmount + $carryForwardAmount - $usedAmount, 2);

            if ($balanceAmount < 0) {
                throw ValidationException::withMessages([
                    'allocated_amount' => 'Allocated leave cannot be less than used leave.',
                ]);
            }

            $allocation->update([
                ...$attributes,
                'allocated_amount' => $allocatedAmount,
                'used_amount' => $usedAmount,
                'balance_amount' => $balanceAmount,
                'carry_forward_amount' => $carryForwardAmount,
                'updated_by' => $actor->id,
            ]);

            return $allocation->fresh() ?? $allocation;
        });
    }

    public function createRequest(array $attributes, User $actor): HrLeaveRequest
    {
        return DB::transaction(function () use ($attributes, $actor): HrLeaveRequest {
            $employee = $this->resolveEmployee($attributes, $actor);
            $leaveType = HrLeaveType::query()
                ->where('company_id', $employee->company_id)
                ->findOrFail($attributes['leave_type_id']);
            $leavePeriod = $this->resolveLeavePeriod((string) $employee->company_id, $attributes, $leaveType);
            $durationAmount = $this->calculateDurationAmount($leaveType, $attributes['from_date'], $attributes['to_date'], (bool) ($attributes['is_half_day'] ?? false), $attributes['duration_amount'] ?? null);

            $request = HrLeaveRequest::create([
                ...Arr::except($attributes, ['action']),
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_period_id' => $leavePeriod->id,
                'requested_by_user_id' => $actor->id,
                'approver_user_id' => $this->resolveApproverUserId($employee),
                'request_number' => $this->resolveRequestNumber((string) $employee->company_id),
                'status' => HrLeaveRequest::STATUS_DRAFT,
                'duration_amount' => $durationAmount,
                'payroll_status' => HrLeaveRequest::PAYROLL_STATUS_OPEN,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if (($attributes['action'] ?? 'submit') === 'submit') {
                return $this->submit($request, $actor->id);
            }

            return $request->fresh(['employee', 'leaveType', 'leavePeriod']) ?? $request;
        });
    }

    public function updateRequest(HrLeaveRequest $leaveRequest, array $attributes, User $actor): HrLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $attributes, $actor): HrLeaveRequest {
            if ($leaveRequest->status !== HrLeaveRequest::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'leave_request' => 'Only draft leave requests can be updated.',
                ]);
            }

            $employee = $leaveRequest->employee()->with('department')->firstOrFail();
            $leaveType = HrLeaveType::query()
                ->where('company_id', $leaveRequest->company_id)
                ->findOrFail($attributes['leave_type_id']);
            $leavePeriod = $this->resolveLeavePeriod((string) $leaveRequest->company_id, $attributes, $leaveType);
            $durationAmount = $this->calculateDurationAmount($leaveType, $attributes['from_date'], $attributes['to_date'], (bool) ($attributes['is_half_day'] ?? false), $attributes['duration_amount'] ?? null);

            $leaveRequest->update([
                ...Arr::except($attributes, ['action']),
                'leave_type_id' => $leaveType->id,
                'leave_period_id' => $leavePeriod->id,
                'approver_user_id' => $this->resolveApproverUserId($employee),
                'duration_amount' => $durationAmount,
                'updated_by' => $actor->id,
            ]);

            if (($attributes['action'] ?? 'save') === 'submit') {
                return $this->submit($leaveRequest->fresh() ?? $leaveRequest, $actor->id);
            }

            return $leaveRequest->fresh(['employee', 'leaveType', 'leavePeriod']) ?? $leaveRequest;
        });
    }

    public function submit(HrLeaveRequest $leaveRequest, ?string $actorId = null): HrLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actorId): HrLeaveRequest {
            $request = HrLeaveRequest::query()
                ->with(['employee.department', 'leaveType', 'leavePeriod'])
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            if (! in_array($request->status, [HrLeaveRequest::STATUS_DRAFT, HrLeaveRequest::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'leave_request' => 'Only draft or rejected leave requests can be submitted.',
                ]);
            }

            $this->ensurePeriodOpen($request->leavePeriod);
            $this->ensureNoOverlap($request);
            $this->ensureDurationAllowed($request);
            $allocation = $this->ensureAllocationEligibility($request);

            $request->forceFill([
                'approver_user_id' => $request->approver_user_id ?: $this->resolveApproverUserId($request->employee),
                'status' => $request->leaveType?->requires_approval ? HrLeaveRequest::STATUS_SUBMITTED : HrLeaveRequest::STATUS_APPROVED,
                'submitted_at' => now(),
                'updated_by' => $actorId,
            ])->save();

            if (! $request->leaveType?->requires_approval) {
                $request = $this->approveInternal($request, $actorId, $allocation);
            } else {
                $request = $request->fresh(['employee', 'approver', 'leaveType', 'leavePeriod']) ?? $request;
                $this->notificationService->notifyLeaveSubmitted($request, $actorId);
            }

            return $request;
        });
    }

    public function approve(HrLeaveRequest $leaveRequest, ?string $actorId = null): HrLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actorId): HrLeaveRequest {
            $request = HrLeaveRequest::query()
                ->with(['employee.department', 'leaveType', 'leavePeriod'])
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            if ($request->status !== HrLeaveRequest::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'leave_request' => 'Only submitted leave requests can be approved.',
                ]);
            }

            $allocation = $this->ensureAllocationEligibility($request);

            return $this->approveInternal($request, $actorId, $allocation);
        });
    }

    public function reject(HrLeaveRequest $leaveRequest, ?string $reason = null, ?string $actorId = null): HrLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $reason, $actorId): HrLeaveRequest {
            $request = HrLeaveRequest::query()
                ->with(['employee', 'approver', 'leaveType'])
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            if ($request->status !== HrLeaveRequest::STATUS_SUBMITTED) {
                throw ValidationException::withMessages([
                    'leave_request' => 'Only submitted leave requests can be rejected.',
                ]);
            }

            $request->update([
                'status' => HrLeaveRequest::STATUS_REJECTED,
                'decision_notes' => $reason,
                'rejected_by_user_id' => $actorId,
                'rejected_at' => now(),
                'updated_by' => $actorId,
            ]);

            $request = $request->fresh(['employee', 'leaveType']) ?? $request;
            $this->notificationService->notifyLeaveDecision($request, 'rejected', $actorId);

            return $request;
        });
    }

    public function cancel(HrLeaveRequest $leaveRequest, ?string $actorId = null): HrLeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $actorId): HrLeaveRequest {
            $request = HrLeaveRequest::query()
                ->with(['employee', 'leaveType'])
                ->lockForUpdate()
                ->findOrFail($leaveRequest->id);

            if (in_array($request->status, [HrLeaveRequest::STATUS_CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'leave_request' => 'Cancelled leave requests cannot be cancelled again.',
                ]);
            }

            if ($request->status === HrLeaveRequest::STATUS_APPROVED) {
                $allocation = $this->findAllocationForRequest($request, true);

                if ($allocation) {
                    $this->applyAllocationUsage($allocation, -1 * (float) $request->duration_amount, $actorId);
                }
            }

            $request->update([
                'status' => HrLeaveRequest::STATUS_CANCELLED,
                'cancelled_by_user_id' => $actorId,
                'cancelled_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $request->fresh(['employee', 'leaveType']) ?? $request;
        });
    }

    private function approveInternal(HrLeaveRequest $request, ?string $actorId, ?HrLeaveAllocation $allocation = null): HrLeaveRequest
    {
        if (! $allocation) {
            $allocation = $this->findAllocationForRequest($request, true);
        }

        if ($allocation) {
            $this->applyAllocationUsage($allocation, (float) $request->duration_amount, $actorId);
        }

        $request->update([
            'status' => HrLeaveRequest::STATUS_APPROVED,
            'approved_by_user_id' => $actorId,
            'approved_at' => now(),
            'decision_notes' => null,
            'updated_by' => $actorId,
        ]);

        $request = $request->fresh(['employee', 'leaveType', 'leavePeriod']) ?? $request;
        $this->notificationService->notifyLeaveDecision($request, 'approved', $actorId);

        return $request;
    }

    private function resolveEmployee(array $attributes, User $actor): HrEmployee
    {
        $employeeId = $attributes['employee_id'] ?? null;

        if ($employeeId) {
            return HrEmployee::query()
                ->where('company_id', $actor->current_company_id)
                ->findOrFail($employeeId);
        }

        $employee = HrEmployee::query()
            ->where('company_id', $actor->current_company_id)
            ->where('user_id', $actor->id)
            ->first();

        if ($employee) {
            return $employee;
        }

        throw ValidationException::withMessages([
            'employee_id' => 'A linked employee record is required for this request.',
        ]);
    }

    private function resolveLeavePeriod(string $companyId, array $attributes, HrLeaveType $leaveType): HrLeavePeriod
    {
        $leavePeriodId = $attributes['leave_period_id'] ?? null;

        if ($leavePeriodId) {
            $period = HrLeavePeriod::query()
                ->where('company_id', $companyId)
                ->findOrFail($leavePeriodId);

            $this->ensureDatesWithinPeriod($attributes['from_date'], $attributes['to_date'], $period);

            return $period;
        }

        $period = HrLeavePeriod::query()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $attributes['from_date'])
            ->whereDate('end_date', '>=', $attributes['to_date'])
            ->orderBy('start_date')
            ->first();

        if ($period) {
            return $period;
        }

        throw ValidationException::withMessages([
            'leave_period_id' => sprintf('No leave period covers the selected dates for %s.', $leaveType->name),
        ]);
    }

    private function ensurePeriodOpen(?HrLeavePeriod $leavePeriod): void
    {
        if ($leavePeriod && $leavePeriod->is_closed) {
            throw ValidationException::withMessages([
                'leave_period_id' => 'The selected leave period is closed.',
            ]);
        }
    }

    private function ensureDatesWithinPeriod(string $fromDate, string $toDate, HrLeavePeriod $period): void
    {
        if ($fromDate < $period->start_date->toDateString() || $toDate > $period->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'leave_period_id' => 'Leave dates must stay within the selected period.',
            ]);
        }
    }

    private function calculateDurationAmount(HrLeaveType $leaveType, string $fromDate, string $toDate, bool $isHalfDay, mixed $manualDuration): float
    {
        $this->ensurePeriodDatesAreValid($fromDate, $toDate);

        if ($leaveType->unit === HrLeaveType::UNIT_HOURS) {
            $duration = round((float) $manualDuration, 2);

            if ($duration <= 0) {
                throw ValidationException::withMessages([
                    'duration_amount' => 'Hours-based leave requests require a positive duration amount.',
                ]);
            }

            return $duration;
        }

        if ($isHalfDay) {
            if ($fromDate !== $toDate) {
                throw ValidationException::withMessages([
                    'to_date' => 'Half-day leave must start and end on the same date.',
                ]);
            }

            return 0.5;
        }

        $days = now()->parse($fromDate)->diffInDays(now()->parse($toDate)) + 1;

        return round((float) $days, 2);
    }

    private function ensureDurationAllowed(HrLeaveRequest $request): void
    {
        $maxConsecutive = $request->leaveType?->max_consecutive_days;

        if ($maxConsecutive !== null && (float) $request->duration_amount > (float) $maxConsecutive) {
            throw ValidationException::withMessages([
                'duration_amount' => sprintf('This leave type allows at most %.2f consecutive day(s).', (float) $maxConsecutive),
            ]);
        }
    }

    private function ensureNoOverlap(HrLeaveRequest $request): void
    {
        $exists = HrLeaveRequest::query()
            ->where('company_id', $request->company_id)
            ->where('employee_id', $request->employee_id)
            ->whereIn('status', [HrLeaveRequest::STATUS_SUBMITTED, HrLeaveRequest::STATUS_APPROVED])
            ->where('id', '!=', $request->id)
            ->whereDate('from_date', '<=', $request->to_date)
            ->whereDate('to_date', '>=', $request->from_date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'from_date' => 'This leave request overlaps an existing submitted or approved leave request.',
            ]);
        }
    }

    private function ensureAllocationEligibility(HrLeaveRequest $request): ?HrLeaveAllocation
    {
        $leaveType = $request->leaveType;

        if (! $leaveType || ! $leaveType->requires_allocation) {
            return null;
        }

        $allocation = $this->findAllocationForRequest($request, true);

        if (! $allocation) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'No leave allocation exists for this employee and leave period.',
            ]);
        }

        $remaining = round((float) $allocation->balance_amount, 2);
        $requested = round((float) $request->duration_amount, 2);

        if (! $leaveType->allow_negative_balance && $remaining < $requested) {
            throw ValidationException::withMessages([
                'duration_amount' => sprintf('Insufficient leave balance. Remaining balance is %.2f.', $remaining),
            ]);
        }

        return $allocation;
    }

    private function findAllocationForRequest(HrLeaveRequest $request, bool $lock = false): ?HrLeaveAllocation
    {
        $query = HrLeaveAllocation::query()
            ->where('company_id', $request->company_id)
            ->where('employee_id', $request->employee_id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('leave_period_id', $request->leave_period_id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function applyAllocationUsage(HrLeaveAllocation $allocation, float $delta, ?string $actorId = null): void
    {
        $usedAmount = round((float) $allocation->used_amount + $delta, 2);
        $balanceAmount = round((float) $allocation->allocated_amount + (float) $allocation->carry_forward_amount - $usedAmount, 2);

        if ($usedAmount < 0) {
            $usedAmount = 0;
            $balanceAmount = round((float) $allocation->allocated_amount + (float) $allocation->carry_forward_amount, 2);
        }

        $allocation->update([
            'used_amount' => $usedAmount,
            'balance_amount' => $balanceAmount,
            'updated_by' => $actorId,
        ]);
    }

    private function resolveApproverUserId(HrEmployee $employee): ?string
    {
        if ($employee->leave_approver_user_id) {
            return (string) $employee->leave_approver_user_id;
        }

        $employee->loadMissing('department', 'managerEmployee');

        if ($employee->department?->leave_approver_user_id) {
            return (string) $employee->department->leave_approver_user_id;
        }

        if ($employee->managerEmployee?->user_id) {
            return (string) $employee->managerEmployee->user_id;
        }

        return null;
    }

    private function resolveRequestNumber(string $companyId): string
    {
        $prefix = 'LEV-';
        $latest = HrLeaveRequest::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_number', 'like', $prefix.'%')
            ->orderByDesc('request_number')
            ->value('request_number');

        $sequence = $latest ? ((int) Str::afterLast((string) $latest, '-')) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function resolveCode(string $modelClass, string $companyId, mixed $proposed, mixed $name, ?string $current = null): ?string
    {
        $candidate = trim((string) $proposed);

        if ($candidate !== '') {
            return $candidate;
        }

        if ($current) {
            return $current;
        }

        $base = Str::upper(Str::limit(Str::slug((string) $name, ''), 12, ''));

        if ($base === '') {
            return null;
        }

        $exists = $modelClass::query()
            ->where('company_id', $companyId)
            ->where('code', $base)
            ->exists();

        if (! $exists) {
            return $base;
        }

        for ($index = 2; $index <= 99; $index++) {
            $candidateCode = Str::limit($base, 9, '').str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            $exists = $modelClass::query()
                ->where('company_id', $companyId)
                ->where('code', $candidateCode)
                ->exists();

            if (! $exists) {
                return $candidateCode;
            }
        }

        return $base.Str::upper(Str::random(2));
    }

    private function ensurePeriodDatesAreValid(string $startDate, string $endDate): void
    {
        if ($endDate < $startDate) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }
    }
}
