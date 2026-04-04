<?php

namespace App\Http\Controllers\Approvals;

use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\ApprovalRequestRejectRequest;
use App\Support\Http\Feedback;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Approvals\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalsDashboardController extends Controller
{
    public function index(
        Request $request,
        ApprovalQueueService $approvalQueueService
    ): Response {
        $this->authorize('viewAny', ApprovalRequest::class);

        $user = $request->user();
        $company = $user?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected,cancelled'],
            'module' => ['nullable', 'string', 'in:sales,purchasing,inventory,accounting,projects'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $approvalQueueService->syncPendingRequests($company, $user?->id);

        $requestsQuery = ApprovalRequest::query()
            ->with([
                'requestedBy:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
            ])
            ->orderByRaw(
                "case when status = 'pending' then 0 else 1 end"
            )
            ->orderByDesc('updated_at');

        if ($user) {
            $requestsQuery = $user->applyDataScopeToQuery($requestsQuery);
        }

        if (! empty($filters['status'])) {
            $requestsQuery->where('status', $filters['status']);
        }

        if (! empty($filters['module'])) {
            $requestsQuery->where('module', $filters['module']);
        }

        [$start, $end] = $this->normalizedDateRange(
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        if ($start && $end) {
            $requestsQuery->whereBetween('requested_at', [$start, $end]);
        } elseif ($start) {
            $requestsQuery->where('requested_at', '>=', $start);
        } elseif ($end) {
            $requestsQuery->where('requested_at', '<=', $end);
        }

        $requests = $requestsQuery
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('approvals/index', [
            'filters' => [
                'status' => $filters['status'] ?? '',
                'module' => $filters['module'] ?? '',
                'start_date' => $filters['start_date'] ?? '',
                'end_date' => $filters['end_date'] ?? '',
            ],
            'metrics' => $approvalQueueService->metrics($company),
            'approvalRequests' => $requests->through(
                fn (ApprovalRequest $approvalRequest) => [
                    'id' => $approvalRequest->id,
                    'module' => $approvalRequest->module,
                    'action' => $approvalRequest->action,
                    'source_type' => class_basename((string) $approvalRequest->source_type),
                    'source_number' => $approvalRequest->source_number,
                    'status' => $approvalRequest->status,
                    'amount' => $approvalRequest->amount !== null
                        ? (float) $approvalRequest->amount
                        : null,
                    'currency_code' => $approvalRequest->currency_code,
                    'risk_level' => $approvalRequest->risk_level,
                    'requested_by' => $approvalRequest->requestedBy?->name,
                    'approved_by' => $approvalRequest->approvedBy?->name,
                    'rejected_by' => $approvalRequest->rejectedBy?->name,
                    'requested_at' => $approvalRequest->requested_at?->toIso8601String(),
                    'approved_at' => $approvalRequest->approved_at?->toIso8601String(),
                    'rejected_at' => $approvalRequest->rejected_at?->toIso8601String(),
                    'rejection_reason' => $approvalRequest->rejection_reason,
                    'can_approve' => $approvalRequest->status === ApprovalRequest::STATUS_PENDING
                        && $user?->hasPermission('approvals.requests.manage')
                        && $approvalQueueService->canApprove($approvalRequest, $user),
                ]
            ),
        ]);
    }

    public function approve(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalQueueService $approvalQueueService
    ): RedirectResponse {
        $this->authorize('approve', $approvalRequest);
        $actor = $request->user();

        if (! $actor) {
            abort(403);
        }

        try {
            $approvalQueueService->approve($approvalRequest, $actor);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with(
                    'error',
                    Feedback::flash(
                        $request,
                        collect($exception->errors())
                            ->flatten()
                            ->first() ?? 'Approval failed.',
                    ),
                );
        }

        return back()->with('success', Feedback::flash($request, 'Approval request approved.'));
    }

    public function reject(
        ApprovalRequestRejectRequest $request,
        ApprovalRequest $approvalRequest,
        ApprovalQueueService $approvalQueueService
    ): RedirectResponse {
        $this->authorize('reject', $approvalRequest);
        $actor = $request->user();

        if (! $actor) {
            abort(403);
        }

        try {
            $approvalQueueService->reject(
                approvalRequest: $approvalRequest,
                actor: $actor,
                reason: $request->validated('reason')
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with(
                    'error',
                    Feedback::flash(
                        $request,
                        collect($exception->errors())
                            ->flatten()
                            ->first() ?? 'Rejection failed.',
                    ),
                );
        }

        return back()->with('success', Feedback::flash($request, 'Approval request rejected.'));
    }

    /**
     * @return array{0: \Carbon\CarbonImmutable|null, 1: \Carbon\CarbonImmutable|null}
     */
    private function normalizedDateRange(
        ?string $startDate,
        ?string $endDate
    ): array {
        $start = null;
        $end = null;

        if (is_string($startDate) && trim($startDate) !== '') {
            try {
                $start = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', trim($startDate))
                    ->startOfDay();
            } catch (\Throwable) {
                $start = null;
            }
        }

        if (is_string($endDate) && trim($endDate) !== '') {
            try {
                $end = \Carbon\CarbonImmutable::createFromFormat('Y-m-d', trim($endDate))
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
