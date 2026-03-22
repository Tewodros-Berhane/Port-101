<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Approvals\ApprovalRequestRejectRequest;
use App\Models\User;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Approvals\Models\ApprovalStep;
use App\Support\Api\ApiQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalRequestsController extends ApiController
{
    public function index(
        Request $request,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('viewAny', ApprovalRequest::class);

        $user = $request->user();
        $company = $user?->currentCompany;

        if (! $user instanceof User || ! $company) {
            abort(403, 'Company context not available.');
        }

        $approvalQueueService->syncPendingRequests($company, $user->id);

        $perPage = ApiQuery::perPage($request);
        $search = trim((string) $request->input('search', ''));
        $status = trim((string) $request->input('status', ''));
        $module = trim((string) $request->input('module', ''));
        $action = trim((string) $request->input('action', ''));
        $riskLevel = trim((string) $request->input('risk_level', ''));
        $startDate = trim((string) $request->input('start_date', ''));
        $endDate = trim((string) $request->input('end_date', ''));
        ['sort' => $sort, 'direction' => $direction] = ApiQuery::sort(
            $request,
            allowed: ['requested_at', 'updated_at', 'source_number', 'module', 'status', 'amount'],
            defaultSort: 'requested_at',
            defaultDirection: 'desc',
        );

        [$start, $end] = $this->normalizedDateRange($startDate, $endDate);

        $requests = ApprovalRequest::query()
            ->with([
                'requestedBy:id,name,email',
                'approvedBy:id,name,email',
                'rejectedBy:id,name,email',
            ])
            ->withCount('steps')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('source_number', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhereHas('requestedBy', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($riskLevel !== '', fn ($query) => $query->where('risk_level', $riskLevel))
            ->when($start && $end, fn ($query) => $query->whereBetween('requested_at', [$start, $end]))
            ->when($start && ! $end, fn ($query) => $query->where('requested_at', '>=', $start))
            ->when(! $start && $end, fn ($query) => $query->where('requested_at', '<=', $end))
            ->tap(fn ($query) => $user->applyDataScopeToQuery($query))
            ->tap(fn ($query) => ApiQuery::applySort($query, $sort, $direction))
            ->paginate($perPage)
            ->withQueryString();

        return $this->respondPaginated(
            paginator: $requests,
            data: collect($requests->items())
                ->map(fn (ApprovalRequest $approvalRequest) => $this->mapApprovalRequest(
                    $approvalRequest,
                    $user,
                    $approvalQueueService,
                    false,
                ))
                ->all(),
            sort: $sort,
            direction: $direction,
            filters: [
                'search' => $search,
                'status' => $status,
                'module' => $module,
                'action' => $action,
                'risk_level' => $riskLevel,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        );
    }

    public function show(
        ApprovalRequest $approvalRequest,
        Request $request,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('view', $approvalRequest);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $approvalRequest->load([
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'rejectedBy:id,name,email',
            'steps.approver:id,name,email',
        ])->loadCount('steps');

        return $this->respond(
            $this->mapApprovalRequest(
                $approvalRequest,
                $user,
                $approvalQueueService,
            ),
        );
    }

    public function approve(
        ApprovalRequest $approvalRequest,
        Request $request,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('approve', $approvalRequest);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $approvalQueueService->approve($approvalRequest, $actor);

        $approvalRequest = $approvalRequest->fresh([
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'rejectedBy:id,name,email',
            'steps.approver:id,name,email',
        ])->loadCount('steps');

        return $this->respond(
            $this->mapApprovalRequest(
                $approvalRequest,
                $actor,
                $approvalQueueService,
            ),
        );
    }

    public function reject(
        ApprovalRequestRejectRequest $request,
        ApprovalRequest $approvalRequest,
        ApprovalQueueService $approvalQueueService,
    ): JsonResponse {
        $this->authorize('reject', $approvalRequest);

        $actor = $request->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        $approvalQueueService->reject(
            approvalRequest: $approvalRequest,
            actor: $actor,
            reason: $request->validated('reason'),
        );

        $approvalRequest = $approvalRequest->fresh([
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'rejectedBy:id,name,email',
            'steps.approver:id,name,email',
        ])->loadCount('steps');

        return $this->respond(
            $this->mapApprovalRequest(
                $approvalRequest,
                $actor,
                $approvalQueueService,
            ),
        );
    }

    private function mapApprovalRequest(
        ApprovalRequest $approvalRequest,
        User $user,
        ApprovalQueueService $approvalQueueService,
        bool $includeSteps = true,
    ): array {
        $payload = [
            'id' => $approvalRequest->id,
            'module' => $approvalRequest->module,
            'action' => $approvalRequest->action,
            'source_type' => class_basename((string) $approvalRequest->source_type),
            'source_type_class' => $approvalRequest->source_type,
            'source_id' => $approvalRequest->source_id,
            'source_number' => $approvalRequest->source_number,
            'status' => $approvalRequest->status,
            'amount' => $approvalRequest->amount !== null ? (float) $approvalRequest->amount : null,
            'currency_code' => $approvalRequest->currency_code,
            'risk_level' => $approvalRequest->risk_level,
            'requested_by_user_id' => $approvalRequest->requested_by_user_id,
            'requested_by_name' => $approvalRequest->requestedBy?->name,
            'requested_by_email' => $approvalRequest->requestedBy?->email,
            'approved_by_user_id' => $approvalRequest->approved_by_user_id,
            'approved_by_name' => $approvalRequest->approvedBy?->name,
            'approved_by_email' => $approvalRequest->approvedBy?->email,
            'rejected_by_user_id' => $approvalRequest->rejected_by_user_id,
            'rejected_by_name' => $approvalRequest->rejectedBy?->name,
            'rejected_by_email' => $approvalRequest->rejectedBy?->email,
            'requested_at' => $approvalRequest->requested_at?->toIso8601String(),
            'approved_at' => $approvalRequest->approved_at?->toIso8601String(),
            'rejected_at' => $approvalRequest->rejected_at?->toIso8601String(),
            'rejection_reason' => $approvalRequest->rejection_reason,
            'metadata' => $approvalRequest->metadata ?? [],
            'steps_count' => (int) ($approvalRequest->steps_count ?? $approvalRequest->steps()->count()),
            'updated_at' => $approvalRequest->updated_at?->toIso8601String(),
            'can_view' => $user->can('view', $approvalRequest),
            'can_approve' => $approvalRequest->status === ApprovalRequest::STATUS_PENDING
                && $user->can('approve', $approvalRequest)
                && $approvalQueueService->canApprove($approvalRequest, $user),
            'can_reject' => $approvalRequest->status === ApprovalRequest::STATUS_PENDING
                && $user->can('reject', $approvalRequest)
                && $approvalQueueService->canApprove($approvalRequest, $user),
        ];

        if (! $includeSteps) {
            return $payload;
        }

        $payload['steps'] = $approvalRequest->relationLoaded('steps')
            ? $approvalRequest->steps->map(fn (ApprovalStep $step) => [
                'id' => $step->id,
                'step_order' => (int) $step->step_order,
                'status' => $step->status,
                'approver_user_id' => $step->approver_user_id,
                'approver_name' => $step->approver?->name,
                'approver_email' => $step->approver?->email,
                'decision_notes' => $step->decision_notes,
                'acted_at' => $step->acted_at?->toIso8601String(),
            ])->values()->all()
            : [];

        return $payload;
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function normalizedDateRange(
        ?string $startDate,
        ?string $endDate,
    ): array {
        $start = null;
        $end = null;

        if (is_string($startDate) && trim($startDate) !== '') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', trim($startDate))
                    ->startOfDay();
            } catch (\Throwable) {
                $start = null;
            }
        }

        if (is_string($endDate) && trim($endDate) !== '') {
            try {
                $end = CarbonImmutable::createFromFormat('Y-m-d', trim($endDate))
                    ->endOfDay();
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
