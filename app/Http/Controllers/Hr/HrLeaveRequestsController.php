<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrLeaveDecisionRequest;
use App\Http\Requests\Hr\HrLeaveRequestStoreRequest;
use App\Http\Requests\Hr\HrLeaveRequestUpdateRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\HrLeaveWorkspaceService;
use App\Modules\Hr\Models\HrEmployee;
use App\Modules\Hr\Models\HrLeaveAllocation;
use App\Modules\Hr\Models\HrLeavePeriod;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Hr\Models\HrLeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HrLeaveRequestsController extends Controller
{
    public function index(Request $request, HrLeaveWorkspaceService $workspaceService): Response
    {
        $this->authorize('viewAny', HrLeaveRequest::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:draft,submitted,approved,rejected,cancelled'],
            'leave_type_id' => ['nullable', 'uuid'],
            'leave_period_id' => ['nullable', 'uuid'],
            'employee_id' => ['nullable', 'uuid'],
        ]);

        $requests = HrLeaveRequest::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'leaveType:id,name,unit,color',
                'leavePeriod:id,name',
                'approver:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'cancelledBy:id,name',
            ])
            ->accessibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['leave_type_id'] ?? null, fn ($query, $leaveTypeId) => $query->where('leave_type_id', $leaveTypeId))
            ->when($filters['leave_period_id'] ?? null, fn ($query, $leavePeriodId) => $query->where('leave_period_id', $leavePeriodId))
            ->when($filters['employee_id'] ?? null, fn ($query, $employeeId) => $query->where('employee_id', $employeeId))
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $allocations = HrLeaveAllocation::query()
            ->with([
                'employee:id,display_name,employee_number',
                'leaveType:id,name,unit,color',
                'leavePeriod:id,name',
            ])
            ->accessibleTo($user)
            ->latest('updated_at')
            ->limit(12)
            ->get();

        $leaveTypes = HrLeaveType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'unit', 'requires_allocation', 'requires_approval', 'is_paid', 'allow_negative_balance', 'max_consecutive_days', 'color']);

        $leavePeriods = HrLeavePeriod::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'is_closed']);

        $linkedEmployeeId = HrEmployee::query()
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->value('id');

        return Inertia::render('hr/leave/index', [
            'summary' => $workspaceService->summary($user),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'leave_type_id' => $filters['leave_type_id'] ?? '',
                'leave_period_id' => $filters['leave_period_id'] ?? '',
                'employee_id' => $filters['employee_id'] ?? '',
            ],
            'statuses' => HrLeaveRequest::STATUSES,
            'leaveTypes' => $leaveTypes->map(fn (HrLeaveType $leaveType) => [
                'id' => $leaveType->id,
                'name' => $leaveType->name,
                'code' => $leaveType->code,
                'unit' => $leaveType->unit,
                'requires_allocation' => (bool) $leaveType->requires_allocation,
                'requires_approval' => (bool) $leaveType->requires_approval,
                'is_paid' => (bool) $leaveType->is_paid,
                'allow_negative_balance' => (bool) $leaveType->allow_negative_balance,
                'max_consecutive_days' => $leaveType->max_consecutive_days !== null ? (float) $leaveType->max_consecutive_days : null,
                'color' => $leaveType->color,
            ])->values()->all(),
            'leavePeriods' => $leavePeriods->map(fn (HrLeavePeriod $leavePeriod) => [
                'id' => $leavePeriod->id,
                'name' => $leavePeriod->name,
                'start_date' => $leavePeriod->start_date?->toDateString(),
                'end_date' => $leavePeriod->end_date?->toDateString(),
                'is_closed' => (bool) $leavePeriod->is_closed,
            ])->values()->all(),
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'linkedEmployeeId' => $linkedEmployeeId,
            'allocations' => $allocations->map(fn (HrLeaveAllocation $allocation) => [
                'id' => $allocation->id,
                'employee_name' => $allocation->employee?->display_name,
                'employee_number' => $allocation->employee?->employee_number,
                'leave_type_name' => $allocation->leaveType?->name,
                'leave_type_unit' => $allocation->leaveType?->unit,
                'leave_period_name' => $allocation->leavePeriod?->name,
                'allocated_amount' => (float) $allocation->allocated_amount,
                'used_amount' => (float) $allocation->used_amount,
                'balance_amount' => (float) $allocation->balance_amount,
                'carry_forward_amount' => (float) $allocation->carry_forward_amount,
                'expires_at' => $allocation->expires_at?->toDateString(),
                'can_edit' => $user->can('update', $allocation),
            ])->values()->all(),
            'requests' => $requests->through(fn (HrLeaveRequest $leaveRequest) => [
                'id' => $leaveRequest->id,
                'request_number' => $leaveRequest->request_number,
                'status' => $leaveRequest->status,
                'employee_name' => $leaveRequest->employee?->display_name,
                'employee_number' => $leaveRequest->employee?->employee_number,
                'leave_type_name' => $leaveRequest->leaveType?->name,
                'leave_type_unit' => $leaveRequest->leaveType?->unit,
                'leave_period_name' => $leaveRequest->leavePeriod?->name,
                'approver_name' => $leaveRequest->approver?->name,
                'approved_by_name' => $leaveRequest->approvedBy?->name,
                'rejected_by_name' => $leaveRequest->rejectedBy?->name,
                'cancelled_by_name' => $leaveRequest->cancelledBy?->name,
                'from_date' => $leaveRequest->from_date?->toDateString(),
                'to_date' => $leaveRequest->to_date?->toDateString(),
                'duration_amount' => (float) $leaveRequest->duration_amount,
                'is_half_day' => (bool) $leaveRequest->is_half_day,
                'reason' => $leaveRequest->reason,
                'decision_notes' => $leaveRequest->decision_notes,
                'submitted_at' => $leaveRequest->submitted_at?->toIso8601String(),
                'approved_at' => $leaveRequest->approved_at?->toIso8601String(),
                'rejected_at' => $leaveRequest->rejected_at?->toIso8601String(),
                'cancelled_at' => $leaveRequest->cancelled_at?->toIso8601String(),
                'can_edit' => $user->can('update', $leaveRequest),
                'can_submit' => $user->can('submit', $leaveRequest),
                'can_approve' => $user->can('approve', $leaveRequest),
                'can_reject' => $user->can('reject', $leaveRequest),
                'can_cancel' => $user->can('cancel', $leaveRequest),
            ]),
            'abilities' => [
                'can_create_request' => $user->can('create', HrLeaveRequest::class),
                'can_manage_leave' => $user->can('create', HrLeaveType::class),
                'can_approve_leave' => $user->hasPermission('hr.leave.approve'),
            ],
        ]);
    }

    public function create(Request $request, HrLeaveWorkspaceService $workspaceService): Response
    {
        $this->authorize('create', HrLeaveRequest::class);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $linkedEmployeeId = HrEmployee::query()
            ->where('company_id', $user->current_company_id)
            ->where('user_id', $user->id)
            ->value('id');

        return Inertia::render('hr/leave/requests/create', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'leaveTypes' => HrLeaveType::query()->orderBy('name')->get(['id', 'name', 'unit', 'requires_allocation', 'requires_approval']),
            'leavePeriods' => HrLeavePeriod::query()->orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'is_closed']),
            'form' => [
                'employee_id' => $linkedEmployeeId ?? '',
                'leave_type_id' => '',
                'leave_period_id' => '',
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
                'duration_amount' => '',
                'is_half_day' => false,
                'reason' => '',
                'action' => 'submit',
            ],
        ]);
    }

    public function store(
        HrLeaveRequestStoreRequest $request,
        HrLeaveService $leaveService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('create', HrLeaveRequest::class);

        try {
            $leaveRequest = $leaveService->createRequest($request->validated(), $request->user());
            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.leave.index')
            ->with('success', sprintf('Leave request %s saved.', $leaveRequest->request_number));
    }

    public function edit(Request $request, HrLeaveRequest $leaveRequest, HrLeaveWorkspaceService $workspaceService): Response
    {
        $this->authorize('update', $leaveRequest);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $leaveRequest->loadMissing(['employee:id,display_name,employee_number', 'leaveType:id,name,unit', 'leavePeriod:id,name,start_date,end_date,is_closed']);

        return Inertia::render('hr/leave/requests/edit', [
            'employeeOptions' => $workspaceService->employeeOptions($user),
            'leaveTypes' => HrLeaveType::query()->orderBy('name')->get(['id', 'name', 'unit', 'requires_allocation', 'requires_approval']),
            'leavePeriods' => HrLeavePeriod::query()->orderByDesc('start_date')->get(['id', 'name', 'start_date', 'end_date', 'is_closed']),
            'requestRecord' => [
                'id' => $leaveRequest->id,
                'employee_id' => $leaveRequest->employee_id,
                'leave_type_id' => $leaveRequest->leave_type_id,
                'leave_period_id' => $leaveRequest->leave_period_id,
                'from_date' => $leaveRequest->from_date?->toDateString(),
                'to_date' => $leaveRequest->to_date?->toDateString(),
                'duration_amount' => $leaveRequest->duration_amount !== null ? (string) $leaveRequest->duration_amount : '',
                'is_half_day' => (bool) $leaveRequest->is_half_day,
                'reason' => $leaveRequest->reason ?? '',
                'action' => 'save',
            ],
        ]);
    }

    public function update(
        HrLeaveRequestUpdateRequest $request,
        HrLeaveRequest $leaveRequest,
        HrLeaveService $leaveService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('update', $leaveRequest);

        try {
            $leaveService->updateRequest($leaveRequest, $request->validated(), $request->user());
            $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('company.hr.leave.index')
            ->with('success', 'Leave request updated.');
    }

    public function submit(Request $request, HrLeaveRequest $leaveRequest, HrLeaveService $leaveService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('submit', $leaveRequest);

        try {
            $leaveService->submit($leaveRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Leave request submitted.');
    }

    public function approve(Request $request, HrLeaveRequest $leaveRequest, HrLeaveService $leaveService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('approve', $leaveRequest);

        try {
            $leaveService->approve($leaveRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Leave request approved.');
    }

    public function reject(HrLeaveDecisionRequest $request, HrLeaveRequest $leaveRequest, HrLeaveService $leaveService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('reject', $leaveRequest);

        try {
            $leaveService->reject($leaveRequest, $request->validated('reason'), $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Leave request rejected.');
    }

    public function cancel(Request $request, HrLeaveRequest $leaveRequest, HrLeaveService $leaveService, ApprovalQueueService $approvalQueueService): RedirectResponse
    {
        $this->authorize('cancel', $leaveRequest);

        try {
            $leaveService->cancel($leaveRequest, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Leave request cancelled.');
    }
}
