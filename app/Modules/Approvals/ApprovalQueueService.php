<?php

namespace App\Modules\Approvals;

use App\Core\Approvals\ApprovalAuthorityService;
use App\Core\Company\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\AccountingManualJournal;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Approvals\Models\ApprovalStep;
use App\Modules\Hr\HrAttendanceService;
use App\Modules\Hr\HrLeaveService;
use App\Modules\Hr\Models\HrAttendanceRequest;
use App\Modules\Hr\Models\HrLeaveRequest;
use App\Modules\Inventory\InventoryCycleCountService;
use App\Modules\Inventory\Models\InventoryCycleCount;
use App\Modules\Projects\Models\ProjectBillable;
use App\Modules\Projects\ProjectBillableWorkflowService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\PurchasingOrderWorkflowService;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesQuote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalQueueService
{
    public function __construct(
        private readonly ApprovalAuthorityService $approvalAuthorityService,
        private readonly PurchasingOrderWorkflowService $purchasingOrderWorkflowService,
        private readonly ProjectBillableWorkflowService $projectBillableWorkflowService,
        private readonly InventoryCycleCountService $inventoryCycleCountService,
        private readonly HrAttendanceService $hrAttendanceService,
        private readonly HrLeaveService $hrLeaveService,
    ) {}

    public function syncPendingRequests(Company $company, ?string $actorId = null): void
    {
        $this->syncSalesQuoteRequests($company, $actorId);
        $this->syncSalesOrderRequests($company, $actorId);
        $this->syncPurchaseOrderRequests($company, $actorId);
        $this->syncAccountingManualJournalRequests($company, $actorId);
        $this->syncProjectBillableRequests($company, $actorId);
        $this->syncInventoryCycleCountRequests($company, $actorId);
        $this->syncHrLeaveRequests($company, $actorId);
        $this->syncHrAttendanceRequests($company, $actorId);
    }

    /**
     * @return array{pending: int, approved_30d: int, rejected_30d: int, approval_rate_30d: float, avg_turnaround_hours_30d: float}
     */
    public function metrics(Company $company): array
    {
        $baseQuery = ApprovalRequest::query()
            ->where('company_id', $company->id);

        $pending = (clone $baseQuery)
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->count();

        $windowStart = now()->subDays(30);

        $approved30d = (clone $baseQuery)
            ->where('status', ApprovalRequest::STATUS_APPROVED)
            ->where('approved_at', '>=', $windowStart)
            ->count();

        $rejected30d = (clone $baseQuery)
            ->where('status', ApprovalRequest::STATUS_REJECTED)
            ->where('rejected_at', '>=', $windowStart)
            ->count();

        $closedCount = $approved30d + $rejected30d;
        $approvalRate = $closedCount > 0
            ? round(($approved30d / $closedCount) * 100, 2)
            : 0.0;

        $turnaroundRows = (clone $baseQuery)
            ->whereIn('status', [
                ApprovalRequest::STATUS_APPROVED,
                ApprovalRequest::STATUS_REJECTED,
            ])
            ->where(function ($query) use ($windowStart): void {
                $query->where('approved_at', '>=', $windowStart)
                    ->orWhere('rejected_at', '>=', $windowStart);
            })
            ->get(['requested_at', 'approved_at', 'rejected_at']);

        $durations = $turnaroundRows
            ->map(function (ApprovalRequest $request): ?float {
                $requestedAt = $request->requested_at;
                $decisionAt = $request->approved_at ?? $request->rejected_at;

                if (! $requestedAt || ! $decisionAt) {
                    return null;
                }

                return round($requestedAt->diffInSeconds($decisionAt) / 3600, 2);
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        $avgTurnaround = $durations->isNotEmpty()
            ? round((float) $durations->avg(), 2)
            : 0.0;

        return [
            'pending' => $pending,
            'approved_30d' => $approved30d,
            'rejected_30d' => $rejected30d,
            'approval_rate_30d' => $approvalRate,
            'avg_turnaround_hours_30d' => $avgTurnaround,
        ];
    }

    public function canApprove(ApprovalRequest $approvalRequest, User $approver): bool
    {
        $company = $approvalRequest->company;

        if (! $company) {
            return false;
        }

        return $this->approvalAuthorityService->canApprove(
            company: $company,
            approver: $approver,
            module: (string) $approvalRequest->module,
            action: (string) $approvalRequest->action,
            context: [
                'requested_by_user_id' => $approvalRequest->requested_by_user_id,
                'amount' => $approvalRequest->amount,
                'risk_level' => $approvalRequest->risk_level,
            ],
        );
    }

    public function approve(ApprovalRequest $approvalRequest, User $actor): void
    {
        DB::transaction(function () use ($approvalRequest, $actor): void {
            $request = ApprovalRequest::query()
                ->where('id', $approvalRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureRequestPending($request);

            if (! $this->canApprove($request, $actor)) {
                throw ValidationException::withMessages([
                    'approval' => 'You do not have approval authority for this request.',
                ]);
            }

            $source = $this->resolveSource($request);

            if (! $source) {
                $this->markCancelled($request, $actor->id);

                throw ValidationException::withMessages([
                    'approval' => 'Approval source record was not found.',
                ]);
            }

            $now = now();

            if ($source instanceof SalesQuote) {
                if ($source->status === SalesQuote::STATUS_CONFIRMED) {
                    throw ValidationException::withMessages([
                        'approval' => 'Confirmed quotes cannot be approved again.',
                    ]);
                }

                $source->update([
                    'status' => SalesQuote::STATUS_APPROVED,
                    'approved_by' => $actor->id,
                    'approved_at' => $now,
                    'rejection_reason' => null,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($source instanceof SalesOrder) {
                $source->update([
                    'approved_by' => $actor->id,
                    'approved_at' => $now,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($source instanceof PurchaseOrder) {
                $source = $this->purchasingOrderWorkflowService->approve($source, $actor->id);
            } elseif ($source instanceof AccountingManualJournal) {
                if ($source->status !== AccountingManualJournal::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'approval' => 'Only draft manual journals can be approved.',
                    ]);
                }

                $source->update([
                    'approval_status' => AccountingManualJournal::APPROVAL_STATUS_APPROVED,
                    'approved_by' => $actor->id,
                    'approved_at' => $now,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_reason' => null,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($source instanceof ProjectBillable) {
                $source = $this->projectBillableWorkflowService->approve($source, $actor->id);
            } elseif ($source instanceof InventoryCycleCount) {
                $source = $this->inventoryCycleCountService->approve($source, $actor->id);
            } elseif ($source instanceof HrLeaveRequest) {
                $source = $this->hrLeaveService->approve($source, $actor->id);
            } elseif ($source instanceof HrAttendanceRequest) {
                $source = $this->hrAttendanceService->approve($source, $actor->id);
            }

            $this->markApproved(
                request: $request,
                approverId: $actor->id,
                approvedAt: $source instanceof PurchaseOrder
                    ? ($source->approved_at ?? $now)
                    : $now,
                actorId: $actor->id,
                preservePayload: true,
            );

            $this->recordDecisionStep(
                request: $request,
                status: ApprovalStep::STATUS_APPROVED,
                approverId: $actor->id,
                notes: null,
                actorId: $actor->id,
                actedAt: $now,
            );
        });
    }

    public function reject(
        ApprovalRequest $approvalRequest,
        User $actor,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($approvalRequest, $actor, $reason): void {
            $request = ApprovalRequest::query()
                ->where('id', $approvalRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureRequestPending($request);

            if (! $this->canApprove($request, $actor)) {
                throw ValidationException::withMessages([
                    'approval' => 'You do not have approval authority for this request.',
                ]);
            }

            $source = $this->resolveSource($request);

            if (! $source) {
                $this->markCancelled($request, $actor->id);

                throw ValidationException::withMessages([
                    'approval' => 'Approval source record was not found.',
                ]);
            }

            $now = now();

            if ($source instanceof SalesQuote) {
                if ($source->status === SalesQuote::STATUS_CONFIRMED) {
                    throw ValidationException::withMessages([
                        'approval' => 'Confirmed quotes cannot be rejected.',
                    ]);
                }

                $source->update([
                    'status' => SalesQuote::STATUS_REJECTED,
                    'rejection_reason' => $reason,
                    'approved_by' => null,
                    'approved_at' => null,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($source instanceof AccountingManualJournal) {
                if ($source->status !== AccountingManualJournal::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'approval' => 'Only draft manual journals can be rejected.',
                    ]);
                }

                $source->update([
                    'approval_status' => AccountingManualJournal::APPROVAL_STATUS_REJECTED,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => $actor->id,
                    'rejected_at' => $now,
                    'rejection_reason' => $reason,
                    'updated_by' => $actor->id,
                ]);
            } elseif ($source instanceof ProjectBillable) {
                $source = $this->projectBillableWorkflowService->reject(
                    billable: $source,
                    reason: $reason,
                    actorId: $actor->id,
                );
            } elseif ($source instanceof InventoryCycleCount) {
                $source = $this->inventoryCycleCountService->reject($source, $reason, $actor->id);
            } elseif ($source instanceof HrLeaveRequest) {
                $source = $this->hrLeaveService->reject($source, $reason, $actor->id);
            } elseif ($source instanceof HrAttendanceRequest) {
                $source = $this->hrAttendanceService->reject($source, $reason, $actor->id);
            }

            $this->markRejected(
                request: $request,
                rejectorId: $actor->id,
                rejectedAt: $now,
                reason: $reason,
                actorId: $actor->id,
                preservePayload: true,
            );

            $this->recordDecisionStep(
                request: $request,
                status: ApprovalStep::STATUS_REJECTED,
                approverId: $actor->id,
                notes: $reason,
                actorId: $actor->id,
                actedAt: $now,
            );
        });
    }

    private function syncSalesQuoteRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = SalesQuote::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $quotes = SalesQuote::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('requires_approval', true);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $quotes,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_SALES,
            action: ApprovalRequest::ACTION_SALES_QUOTE_APPROVAL,
            isPending: fn (SalesQuote $quote) => (bool) $quote->requires_approval
                && ! $quote->approved_at
                && $quote->status === SalesQuote::STATUS_SENT,
            isApproved: fn (SalesQuote $quote) => $quote->approved_at !== null
                || in_array($quote->status, [
                    SalesQuote::STATUS_APPROVED,
                    SalesQuote::STATUS_CONFIRMED,
                ], true),
            isRejected: fn (SalesQuote $quote) => $quote->status === SalesQuote::STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    private function syncSalesOrderRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = SalesOrder::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $orders = SalesOrder::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('requires_approval', true);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $orders,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_SALES,
            action: ApprovalRequest::ACTION_SALES_ORDER_APPROVAL,
            isPending: fn (SalesOrder $order) => (bool) $order->requires_approval
                && ! $order->approved_at
                && $order->status === SalesOrder::STATUS_DRAFT,
            isApproved: fn (SalesOrder $order) => $order->approved_at !== null,
            isRejected: fn (SalesOrder $order) => false,
            actorId: $actorId,
        );
    }

    private function syncPurchaseOrderRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = PurchaseOrder::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $orders = PurchaseOrder::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('requires_approval', true);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $approvedStatuses = [
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_ORDERED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_BILLED,
            PurchaseOrder::STATUS_CLOSED,
        ];

        $this->syncRequestsForSources(
            company: $company,
            sources: $orders,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_PURCHASING,
            action: ApprovalRequest::ACTION_PURCHASE_ORDER_APPROVAL,
            isPending: fn (PurchaseOrder $order) => (bool) $order->requires_approval
                && ! $order->approved_at
                && $order->status === PurchaseOrder::STATUS_DRAFT,
            isApproved: fn (PurchaseOrder $order) => $order->approved_at !== null
                || in_array($order->status, $approvedStatuses, true),
            isRejected: fn (PurchaseOrder $order) => false,
            actorId: $actorId,
        );
    }

    private function syncAccountingManualJournalRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = AccountingManualJournal::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $manualJournals = AccountingManualJournal::query()
            ->with('lines:id,manual_journal_id,debit')
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('requires_approval', true);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $manualJournals,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_ACCOUNTING,
            action: ApprovalRequest::ACTION_ACCOUNTING_MANUAL_JOURNAL_APPROVAL,
            isPending: fn (AccountingManualJournal $manualJournal) => (bool) $manualJournal->requires_approval
                && $manualJournal->status === AccountingManualJournal::STATUS_DRAFT
                && $manualJournal->approval_status === AccountingManualJournal::APPROVAL_STATUS_PENDING,
            isApproved: fn (AccountingManualJournal $manualJournal) => $manualJournal->approval_status === AccountingManualJournal::APPROVAL_STATUS_APPROVED
                || $manualJournal->approved_at !== null,
            isRejected: fn (AccountingManualJournal $manualJournal) => $manualJournal->approval_status === AccountingManualJournal::APPROVAL_STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    private function syncProjectBillableRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = ProjectBillable::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $billables = ProjectBillable::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('approval_status', ProjectBillable::APPROVAL_STATUS_PENDING);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $billables,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_PROJECTS,
            action: ApprovalRequest::ACTION_PROJECT_BILLABLE_APPROVAL,
            isPending: fn (ProjectBillable $billable) => $billable->status === ProjectBillable::STATUS_READY
                && $billable->approval_status === ProjectBillable::APPROVAL_STATUS_PENDING
                && ! $billable->invoice_id,
            isApproved: fn (ProjectBillable $billable) => $billable->status === ProjectBillable::STATUS_APPROVED
                && $billable->approval_status === ProjectBillable::APPROVAL_STATUS_APPROVED,
            isRejected: fn (ProjectBillable $billable) => $billable->approval_status === ProjectBillable::APPROVAL_STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    private function syncInventoryCycleCountRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = InventoryCycleCount::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $cycleCounts = InventoryCycleCount::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('requires_approval', true);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $cycleCounts,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_INVENTORY,
            action: ApprovalRequest::ACTION_INVENTORY_CYCLE_COUNT_APPROVAL,
            isPending: fn (InventoryCycleCount $cycleCount) => (bool) $cycleCount->requires_approval
                && $cycleCount->status === InventoryCycleCount::STATUS_REVIEWED
                && $cycleCount->approval_status === InventoryCycleCount::APPROVAL_STATUS_PENDING,
            isApproved: fn (InventoryCycleCount $cycleCount) => $cycleCount->approval_status === InventoryCycleCount::APPROVAL_STATUS_APPROVED
                || $cycleCount->status === InventoryCycleCount::STATUS_POSTED,
            isRejected: fn (InventoryCycleCount $cycleCount) => $cycleCount->approval_status === InventoryCycleCount::APPROVAL_STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    private function syncHrLeaveRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = HrLeaveRequest::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $leaveRequests = HrLeaveRequest::query()
            ->with(['leaveType:id,name,requires_approval'])
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('status', HrLeaveRequest::STATUS_SUBMITTED);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $leaveRequests,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_HR,
            action: ApprovalRequest::ACTION_HR_LEAVE_APPROVAL,
            isPending: fn (HrLeaveRequest $leaveRequest) => $leaveRequest->status === HrLeaveRequest::STATUS_SUBMITTED
                && (bool) ($leaveRequest->leaveType?->requires_approval ?? true),
            isApproved: fn (HrLeaveRequest $leaveRequest) => $leaveRequest->status === HrLeaveRequest::STATUS_APPROVED,
            isRejected: fn (HrLeaveRequest $leaveRequest) => $leaveRequest->status === HrLeaveRequest::STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    private function syncHrAttendanceRequests(Company $company, ?string $actorId = null): void
    {
        $sourceType = HrAttendanceRequest::class;

        $trackedSourceIds = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->pluck('source_id');

        $attendanceRequests = HrAttendanceRequest::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($trackedSourceIds): void {
                $query->where('status', HrAttendanceRequest::STATUS_SUBMITTED);

                if ($trackedSourceIds->isNotEmpty()) {
                    $query->orWhereIn('id', $trackedSourceIds->all());
                }
            })
            ->get();

        $this->syncRequestsForSources(
            company: $company,
            sources: $attendanceRequests,
            sourceType: $sourceType,
            module: ApprovalRequest::MODULE_HR,
            action: ApprovalRequest::ACTION_HR_ATTENDANCE_APPROVAL,
            isPending: fn (HrAttendanceRequest $attendanceRequest) => $attendanceRequest->status === HrAttendanceRequest::STATUS_SUBMITTED,
            isApproved: fn (HrAttendanceRequest $attendanceRequest) => $attendanceRequest->status === HrAttendanceRequest::STATUS_APPROVED,
            isRejected: fn (HrAttendanceRequest $attendanceRequest) => $attendanceRequest->status === HrAttendanceRequest::STATUS_REJECTED,
            allowRejectedReopen: true,
            actorId: $actorId,
        );
    }

    /**
     * @param  Collection<int, Model>  $sources
     * @param  callable(Model): bool  $isPending
     * @param  callable(Model): bool  $isApproved
     * @param  callable(Model): bool  $isRejected
     */
    private function syncRequestsForSources(
        Company $company,
        Collection $sources,
        string $sourceType,
        string $module,
        string $action,
        callable $isPending,
        callable $isApproved,
        callable $isRejected,
        bool $allowRejectedReopen = false,
        ?string $actorId = null,
    ): void {
        $existingRequests = ApprovalRequest::query()
            ->where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->get()
            ->keyBy('source_id');

        $sourceIds = [];

        foreach ($sources as $source) {
            $sourceId = (string) $source->getKey();
            $sourceIds[] = $sourceId;

            $request = $existingRequests->get($sourceId);
            $payload = $this->buildPayload(
                company: $company,
                module: $module,
                action: $action,
                source: $source,
                actorId: $actorId,
            );

            if ($isPending($source)) {
                if (! $request) {
                    $request = ApprovalRequest::create([
                        ...$payload,
                        'status' => ApprovalRequest::STATUS_PENDING,
                        'approved_by_user_id' => null,
                        'approved_at' => null,
                        'rejected_by_user_id' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]);

                    $this->appendPendingStep($request, $actorId);

                    continue;
                }

                if (
                    $request->status === ApprovalRequest::STATUS_REJECTED
                    && (
                        ! $allowRejectedReopen
                        || ! $this->sourceChangedAfterDecision($source, $request)
                    )
                ) {
                    $this->updatePayloadOnly($request, $payload, $actorId);

                    continue;
                }

                if (
                    $request->status === ApprovalRequest::STATUS_APPROVED
                    && ! $this->sourceChangedAfterDecision($source, $request)
                ) {
                    $this->updatePayloadOnly($request, $payload, $actorId);

                    continue;
                }

                if ($request->status !== ApprovalRequest::STATUS_PENDING) {
                    $request->update([
                        ...$payload,
                        'status' => ApprovalRequest::STATUS_PENDING,
                        'approved_by_user_id' => null,
                        'approved_at' => null,
                        'rejected_by_user_id' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                        'updated_by' => $actorId,
                    ]);

                    $this->appendPendingStep($request, $actorId);

                    continue;
                }

                $this->updatePayloadOnly($request, $payload, $actorId);

                continue;
            }

            if ($isApproved($source)) {
                if (! $request) {
                    $request = ApprovalRequest::create([
                        ...$payload,
                        'status' => ApprovalRequest::STATUS_APPROVED,
                        'approved_by_user_id' => $this->sourceApprovedBy($source),
                        'approved_at' => $source->getAttribute('approved_at') ?: now(),
                        'rejected_by_user_id' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ]);

                    $this->recordDecisionStep(
                        request: $request,
                        status: ApprovalStep::STATUS_APPROVED,
                        approverId: $request->approved_by_user_id,
                        notes: null,
                        actorId: $actorId,
                        actedAt: $request->approved_at,
                    );

                    continue;
                }

                if ($request->status !== ApprovalRequest::STATUS_APPROVED) {
                    $this->markApproved(
                        request: $request,
                        approverId: $this->sourceApprovedBy($source),
                        approvedAt: $source->getAttribute('approved_at') ?: now(),
                        actorId: $actorId,
                        preservePayload: false,
                        payload: $payload,
                    );

                    $this->recordDecisionStep(
                        request: $request,
                        status: ApprovalStep::STATUS_APPROVED,
                        approverId: $this->sourceApprovedBy($source),
                        notes: null,
                        actorId: $actorId,
                        actedAt: $source->getAttribute('approved_at') ?: now(),
                    );

                    continue;
                }

                $this->updatePayloadOnly($request, $payload, $actorId);

                continue;
            }

            if ($isRejected($source)) {
                if (! $request) {
                    $request = ApprovalRequest::create([
                        ...$payload,
                        'status' => ApprovalRequest::STATUS_REJECTED,
                        'approved_by_user_id' => null,
                        'approved_at' => null,
                        'rejected_by_user_id' => null,
                        'rejected_at' => now(),
                        'rejection_reason' => $this->sourceRejectionReason($source),
                    ]);

                    $this->recordDecisionStep(
                        request: $request,
                        status: ApprovalStep::STATUS_REJECTED,
                        approverId: null,
                        notes: $request->rejection_reason,
                        actorId: $actorId,
                        actedAt: $request->rejected_at,
                    );

                    continue;
                }

                if ($request->status !== ApprovalRequest::STATUS_REJECTED) {
                    $this->markRejected(
                        request: $request,
                        rejectorId: null,
                        rejectedAt: now(),
                        reason: $this->sourceRejectionReason($source),
                        actorId: $actorId,
                        preservePayload: false,
                        payload: $payload,
                    );

                    $this->recordDecisionStep(
                        request: $request,
                        status: ApprovalStep::STATUS_REJECTED,
                        approverId: null,
                        notes: $this->sourceRejectionReason($source),
                        actorId: $actorId,
                        actedAt: now(),
                    );

                    continue;
                }

                $this->updatePayloadOnly($request, $payload, $actorId);

                continue;
            }

            if ($request && $request->status === ApprovalRequest::STATUS_PENDING) {
                $this->markCancelled($request, $actorId);
            }
        }

        foreach ($existingRequests as $sourceId => $request) {
            if (
                ! in_array((string) $sourceId, $sourceIds, true)
                && $request->status === ApprovalRequest::STATUS_PENDING
            ) {
                $this->markCancelled($request, $actorId);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        Company $company,
        string $module,
        string $action,
        Model $source,
        ?string $actorId = null,
    ): array {
        $amount = $this->sourceAmount($source);

        return [
            'company_id' => $company->id,
            'module' => $module,
            'action' => $action,
            'source_type' => $source::class,
            'source_id' => (string) $source->getKey(),
            'source_number' => $this->sourceNumber($source),
            'requested_by_user_id' => $source instanceof HrLeaveRequest || $source instanceof HrAttendanceRequest
                ? ($source->requested_by_user_id ?: $source->getAttribute('created_by') ?: null)
                : ($source->getAttribute('created_by') ?: null),
            'requested_at' => $source->getAttribute('created_at') ?: now(),
            'amount' => $amount,
            'currency_code' => (string) ($company->currency_code ?: 'USD'),
            'risk_level' => $this->riskLevelForAmount($amount),
            'metadata' => [
                'source_status' => $this->sourceStatus($source),
                'source_reference' => $this->sourceNumber($source),
                'approver_user_id' => $source instanceof HrLeaveRequest || $source instanceof HrAttendanceRequest
                    ? $source->approver_user_id
                    : null,
            ],
            'created_by' => $source->getAttribute('created_by') ?: $actorId,
            'updated_by' => $actorId,
        ];
    }

    private function sourceNumber(Model $source): ?string
    {
        if ($source instanceof SalesQuote) {
            return (string) $source->quote_number;
        }

        if ($source instanceof SalesOrder) {
            return (string) $source->order_number;
        }

        if ($source instanceof PurchaseOrder) {
            return (string) $source->order_number;
        }

        if ($source instanceof AccountingManualJournal) {
            return (string) $source->entry_number;
        }

        if ($source instanceof ProjectBillable) {
            return (string) ($source->project_id ?: $source->id);
        }

        if ($source instanceof InventoryCycleCount) {
            return (string) $source->reference;
        }

        if ($source instanceof HrLeaveRequest) {
            return (string) $source->request_number;
        }

        if ($source instanceof HrAttendanceRequest) {
            return (string) $source->request_number;
        }

        return null;
    }

    private function sourceStatus(Model $source): string
    {
        $status = $source->getAttribute('status');

        return is_string($status)
            ? $status
            : 'unknown';
    }

    private function sourceAmount(Model $source): ?float
    {
        $amount = $source->getAttribute('grand_total');

        if ($amount === null) {
            if ($source instanceof AccountingManualJournal) {
                return round((float) $source->lines->sum('debit'), 2);
            }

            if ($source instanceof ProjectBillable) {
                return round((float) $source->amount, 2);
            }

            if ($source instanceof InventoryCycleCount) {
                return round((float) $source->total_absolute_variance_value, 2);
            }

            if ($source instanceof HrLeaveRequest) {
                return round((float) $source->duration_amount, 2);
            }

            if ($source instanceof HrAttendanceRequest) {
                return (float) Carbon::parse($source->from_date)->diffInDays(Carbon::parse($source->to_date)) + 1.0;
            }

            return null;
        }

        return round((float) $amount, 2);
    }

    private function sourceApprovedBy(Model $source): ?string
    {
        return $source->getAttribute('approved_by')
            ?: $source->getAttribute('approved_by_user_id')
            ?: null;
    }

    private function sourceRejectionReason(Model $source): ?string
    {
        $reason = $source->getAttribute('rejection_reason')
            ?: $source->getAttribute('decision_notes')
            ?: null;

        return $reason !== null
            ? (string) $reason
            : null;
    }

    private function riskLevelForAmount(?float $amount): string
    {
        if ($amount === null) {
            return ApprovalRequest::RISK_LOW;
        }

        if ($amount >= 50000) {
            return ApprovalRequest::RISK_CRITICAL;
        }

        if ($amount >= 10000) {
            return ApprovalRequest::RISK_HIGH;
        }

        if ($amount >= 1000) {
            return ApprovalRequest::RISK_MEDIUM;
        }

        return ApprovalRequest::RISK_LOW;
    }

    private function sourceChangedAfterDecision(Model $source, ApprovalRequest $request): bool
    {
        $sourceUpdatedAt = $source->getAttribute('updated_at');
        $decisionAt = $request->approved_at
            ?? $request->rejected_at
            ?? $request->updated_at;

        if (! $sourceUpdatedAt || ! $decisionAt) {
            return true;
        }

        return ! $sourceUpdatedAt->lt($decisionAt);
    }

    private function ensureRequestPending(ApprovalRequest $request): void
    {
        if ($request->status === ApprovalRequest::STATUS_PENDING) {
            return;
        }

        throw ValidationException::withMessages([
            'approval' => 'Only pending approval requests can be processed.',
        ]);
    }

    private function resolveSource(ApprovalRequest $request): ?Model
    {
        return match ($request->source_type) {
            SalesQuote::class => SalesQuote::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            SalesOrder::class => SalesOrder::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            PurchaseOrder::class => PurchaseOrder::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            AccountingManualJournal::class => AccountingManualJournal::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            ProjectBillable::class => ProjectBillable::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            InventoryCycleCount::class => InventoryCycleCount::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            HrLeaveRequest::class => HrLeaveRequest::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            HrAttendanceRequest::class => HrAttendanceRequest::query()
                ->where('company_id', $request->company_id)
                ->find($request->source_id),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function markApproved(
        ApprovalRequest $request,
        ?string $approverId,
        $approvedAt,
        ?string $actorId = null,
        bool $preservePayload = false,
        ?array $payload = null,
    ): void {
        $request->update([
            ...($preservePayload ? [] : ($payload ?? [])),
            'status' => ApprovalRequest::STATUS_APPROVED,
            'approved_by_user_id' => $approverId,
            'approved_at' => $approvedAt,
            'rejected_by_user_id' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function markRejected(
        ApprovalRequest $request,
        ?string $rejectorId,
        $rejectedAt,
        ?string $reason,
        ?string $actorId = null,
        bool $preservePayload = false,
        ?array $payload = null,
    ): void {
        $request->update([
            ...($preservePayload ? [] : ($payload ?? [])),
            'status' => ApprovalRequest::STATUS_REJECTED,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'rejected_by_user_id' => $rejectorId,
            'rejected_at' => $rejectedAt,
            'rejection_reason' => $reason,
            'updated_by' => $actorId,
        ]);
    }

    private function markCancelled(ApprovalRequest $request, ?string $actorId = null): void
    {
        $request->update([
            'status' => ApprovalRequest::STATUS_CANCELLED,
            'updated_by' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updatePayloadOnly(
        ApprovalRequest $request,
        array $payload,
        ?string $actorId = null,
    ): void {
        $request->update([
            ...$payload,
            'status' => $request->status,
            'updated_by' => $actorId,
        ]);
    }

    private function appendPendingStep(ApprovalRequest $request, ?string $actorId = null): void
    {
        $hasPendingStep = $request->steps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->exists();

        if ($hasPendingStep) {
            return;
        }

        $nextOrder = ((int) $request->steps()->max('step_order')) + 1;

        $request->steps()->create([
            'company_id' => $request->company_id,
            'step_order' => $nextOrder,
            'status' => ApprovalStep::STATUS_PENDING,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    private function recordDecisionStep(
        ApprovalRequest $request,
        string $status,
        ?string $approverId,
        ?string $notes,
        ?string $actorId,
        $actedAt,
    ): void {
        $step = $request->steps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->orderBy('step_order')
            ->first();

        if (! $step) {
            $step = $request->steps()->create([
                'company_id' => $request->company_id,
                'step_order' => ((int) $request->steps()->max('step_order')) + 1,
                'status' => ApprovalStep::STATUS_PENDING,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        $step->update([
            'approver_user_id' => $approverId,
            'status' => $status,
            'decision_notes' => $notes,
            'acted_at' => $actedAt,
            'updated_by' => $actorId,
        ]);
    }
}
