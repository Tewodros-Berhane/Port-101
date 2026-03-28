<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\HrLeaveDecisionRequest;
use App\Http\Requests\Hr\HrLeaveRequestStoreRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrLeaveRequestsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrLeaveRequest::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $status = trim((string) $request->input('status', ''));
        $employeeId = trim((string) $request->input('employee_id', ''));
        $leaveTypeId = trim((string) $request->input('leave_type_id', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['from_date', 'to_date', 'created_at', 'status', 'request_number'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $requests = HrLeaveRequest::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'leaveType:id,name,unit',
                'leavePeriod:id,name',
                'approver:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
            ])
            ->accessibleTo($user)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->when($leaveTypeId !== '', fn ($query) => $query->where('leave_type_id', $leaveTypeId))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $requests,
            data: collect($requests->items())->map(fn (HrLeaveRequest $leaveRequest) => $this->mapLeaveRequest($leaveRequest, $user))->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'status' => $status,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
            ],
        );
    }

    public function store(
        HrLeaveRequestStoreRequest $request,
        HrLeaveService $leaveService,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('create', HrLeaveRequest::class);

        $leaveRequest = $leaveService->createRequest($request->validated(), $request->user());
        $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);

        return $this->respond($this->mapLeaveRequest($leaveRequest->load([
            'employee:id,display_name,employee_number,user_id',
            'leaveType:id,name,unit',
            'leavePeriod:id,name',
            'approver:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
        ]), $request->user()), 201);
    }

    public function approve(
        Request $request,
        HrLeaveRequest $leaveRequest,
        HrLeaveService $leaveService,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('approve', $leaveRequest);

        $leaveService->approve($leaveRequest, $request->user()?->id);
        $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);

        return $this->respond($this->mapLeaveRequest($leaveRequest->fresh([
            'employee:id,display_name,employee_number,user_id',
            'leaveType:id,name,unit',
            'leavePeriod:id,name',
            'approver:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
        ]), $request->user()));
    }

    public function reject(
        HrLeaveDecisionRequest $request,
        HrLeaveRequest $leaveRequest,
        HrLeaveService $leaveService,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('reject', $leaveRequest);

        $leaveService->reject($leaveRequest, $request->validated('reason'), $request->user()?->id);
        $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);

        return $this->respond($this->mapLeaveRequest($leaveRequest->fresh([
            'employee:id,display_name,employee_number,user_id',
            'leaveType:id,name,unit',
            'leavePeriod:id,name',
            'approver:id,name',
            'approvedBy:id,name',
            'rejectedBy:id,name',
        ]), $request->user()));
    }

    private function mapLeaveRequest(HrLeaveRequest $leaveRequest, ?User $user): array
    {
        return [
            'id' => $leaveRequest->id,
            'request_number' => $leaveRequest->request_number,
            'status' => $leaveRequest->status,
            'employee_id' => $leaveRequest->employee_id,
            'employee_name' => $leaveRequest->employee?->display_name,
            'employee_number' => $leaveRequest->employee?->employee_number,
            'leave_type_id' => $leaveRequest->leave_type_id,
            'leave_type_name' => $leaveRequest->leaveType?->name,
            'leave_type_unit' => $leaveRequest->leaveType?->unit,
            'leave_period_id' => $leaveRequest->leave_period_id,
            'leave_period_name' => $leaveRequest->leavePeriod?->name,
            'approver_user_id' => $leaveRequest->approver_user_id,
            'approver_name' => $leaveRequest->approver?->name,
            'approved_by_user_id' => $leaveRequest->approved_by_user_id,
            'approved_by_name' => $leaveRequest->approvedBy?->name,
            'rejected_by_user_id' => $leaveRequest->rejected_by_user_id,
            'rejected_by_name' => $leaveRequest->rejectedBy?->name,
            'from_date' => $leaveRequest->from_date?->toDateString(),
            'to_date' => $leaveRequest->to_date?->toDateString(),
            'duration_amount' => (float) $leaveRequest->duration_amount,
            'is_half_day' => (bool) $leaveRequest->is_half_day,
            'reason' => $leaveRequest->reason,
            'decision_notes' => $leaveRequest->decision_notes,
            'payroll_status' => $leaveRequest->payroll_status,
            'submitted_at' => $leaveRequest->submitted_at?->toIso8601String(),
            'approved_at' => $leaveRequest->approved_at?->toIso8601String(),
            'rejected_at' => $leaveRequest->rejected_at?->toIso8601String(),
            'can_approve' => $user?->can('approve', $leaveRequest) ?? false,
            'can_reject' => $user?->can('reject', $leaveRequest) ?? false,
            'can_cancel' => $user?->can('cancel', $leaveRequest) ?? false,
        ];
    }
}
