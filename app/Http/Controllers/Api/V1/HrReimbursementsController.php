<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Hr\HrReimbursementClaimStoreRequest;
use App\Http\Requests\Hr\HrReimbursementDecisionRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Hr\HrReimbursementService;
use App\Modules\Hr\Models\HrReimbursementClaim;
use App\Modules\Hr\Models\HrReimbursementClaimLine;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrReimbursementsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HrReimbursementClaim::class);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $perPage = ApiQuery::perPage($request);
        $status = trim((string) $request->input('status', ''));
        $employeeId = trim((string) $request->input('employee_id', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['submitted_at', 'created_at', 'claim_number', 'status', 'total_amount'],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $claims = HrReimbursementClaim::query()
            ->with([
                'employee:id,display_name,employee_number,user_id',
                'currency:id,code',
                'approver:id,name',
                'managerApprover:id,name',
                'financeApprover:id,name',
            ])
            ->withCount('lines')
            ->accessibleTo($user)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($employeeId !== '', fn ($query) => $query->where('employee_id', $employeeId))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $claims,
            data: collect($claims->items())->map(fn (HrReimbursementClaim $claim) => $this->mapClaim($claim, $user))->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'status' => $status,
                'employee_id' => $employeeId,
            ],
        );
    }

    public function store(
        HrReimbursementClaimStoreRequest $request,
        HrReimbursementService $service,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('create', HrReimbursementClaim::class);

        $claim = $service->createClaim($request->validated(), $request->user());
        $approvalQueueService->syncPendingRequests($request->user()->currentCompany, $request->user()->id);

        return $this->respond($this->mapClaim($claim->fresh([
            'employee:id,display_name,employee_number,user_id',
            'currency:id,code',
            'approver:id,name',
            'managerApprover:id,name',
            'financeApprover:id,name',
            'lines.category:id,name',
        ])->loadCount('lines'), $request->user()), 201);
    }

    public function submit(
        Request $request,
        HrReimbursementClaim $claim,
        HrReimbursementService $service,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('submit', $claim);

        $service->submit($claim, $request->user()?->id);
        $approvalQueueService->syncPendingRequests($request->user()?->currentCompany, $request->user()?->id);

        return $this->respond($this->mapClaim($claim->fresh([
            'employee:id,display_name,employee_number,user_id',
            'currency:id,code',
            'approver:id,name',
            'managerApprover:id,name',
            'financeApprover:id,name',
            'lines.category:id,name',
        ])->loadCount('lines'), $request->user()));
    }

    public function approve(
        Request $request,
        HrReimbursementClaim $claim,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('approve', $claim);

        $approvalQueueService->approve($this->approvalRequestForClaim($claim), $request->user());

        return $this->respond($this->mapClaim($claim->fresh([
            'employee:id,display_name,employee_number,user_id',
            'currency:id,code',
            'approver:id,name',
            'managerApprover:id,name',
            'financeApprover:id,name',
            'lines.category:id,name',
        ])->loadCount('lines'), $request->user()));
    }

    public function reject(
        HrReimbursementDecisionRequest $request,
        HrReimbursementClaim $claim,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('reject', $claim);

        $approvalQueueService->reject(
            $this->approvalRequestForClaim($claim),
            $request->user(),
            $request->validated('reason'),
        );

        return $this->respond($this->mapClaim($claim->fresh([
            'employee:id,display_name,employee_number,user_id',
            'currency:id,code',
            'approver:id,name',
            'managerApprover:id,name',
            'financeApprover:id,name',
            'lines.category:id,name',
        ])->loadCount('lines'), $request->user()));
    }

    private function approvalRequestForClaim(HrReimbursementClaim $claim): ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('company_id', $claim->company_id)
            ->where('source_type', HrReimbursementClaim::class)
            ->where('source_id', $claim->id)
            ->firstOrFail();
    }

    private function mapClaim(HrReimbursementClaim $claim, ?User $user): array
    {
        return [
            'id' => $claim->id,
            'claim_number' => $claim->claim_number,
            'status' => $claim->status,
            'employee_id' => $claim->employee_id,
            'employee_name' => $claim->employee?->display_name,
            'employee_number' => $claim->employee?->employee_number,
            'currency_id' => $claim->currency_id,
            'currency_code' => $claim->currency?->code,
            'total_amount' => (float) $claim->total_amount,
            'project_id' => $claim->project_id,
            'notes' => $claim->notes,
            'decision_notes' => $claim->decision_notes,
            'submitted_at' => $claim->submitted_at?->toIso8601String(),
            'approved_at' => $claim->approved_at?->toIso8601String(),
            'rejected_at' => $claim->rejected_at?->toIso8601String(),
            'approver_user_id' => $claim->approver_user_id,
            'approver_name' => $claim->approver?->name,
            'manager_approver_name' => $claim->managerApprover?->name,
            'finance_approver_name' => $claim->financeApprover?->name,
            'accounting_invoice_id' => $claim->accounting_invoice_id,
            'accounting_payment_id' => $claim->accounting_payment_id,
            'lines_count' => (int) ($claim->lines_count ?? $claim->lines()->count()),
            'lines' => $claim->relationLoaded('lines')
                ? $claim->lines->map(fn (HrReimbursementClaimLine $line) => [
                    'id' => $line->id,
                    'category_id' => $line->category_id,
                    'category_name' => $line->category?->name,
                    'expense_date' => $line->expense_date?->toDateString(),
                    'description' => $line->description,
                    'amount' => (float) $line->amount,
                    'tax_amount' => (float) $line->tax_amount,
                    'project_id' => $line->project_id,
                ])->values()->all()
                : [],
            'can_submit' => $user?->can('submit', $claim) ?? false,
            'can_approve' => $user?->can('approve', $claim) ?? false,
            'can_reject' => $user?->can('reject', $claim) ?? false,
        ];
    }
}
