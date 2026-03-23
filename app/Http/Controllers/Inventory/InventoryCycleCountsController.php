<?php

namespace App\Http\Controllers\Inventory;

use App\Core\MasterData\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryCycleCountStoreRequest;
use App\Http\Requests\Inventory\InventoryCycleCountUpdateRequest;
use App\Modules\Approvals\ApprovalQueueService;
use App\Modules\Inventory\InventoryCycleCountService;
use App\Modules\Inventory\Models\InventoryCycleCount;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryWarehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCycleCountsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryCycleCount::class);

        $user = $request->user();
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:draft,in_progress,reviewed,posted,cancelled'],
            'approval_status' => ['nullable', 'string', 'in:not_required,pending,approved,rejected'],
        ]);

        $query = InventoryCycleCount::query()
            ->with(['warehouse:id,name,code', 'location:id,name,code'])
            ->latest('created_at')
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        $counts = $query->paginate(20)->withQueryString();

        $baseQuery = InventoryCycleCount::query()
            ->when($user, fn ($builder) => $user->applyDataScopeToQuery($builder));

        return Inertia::render('inventory/cycle-counts/index', [
            'filters' => [
                'status' => $filters['status'] ?? '',
                'approval_status' => $filters['approval_status'] ?? '',
            ],
            'metrics' => [
                'open' => (clone $baseQuery)
                    ->whereIn('status', [
                        InventoryCycleCount::STATUS_DRAFT,
                        InventoryCycleCount::STATUS_IN_PROGRESS,
                        InventoryCycleCount::STATUS_REVIEWED,
                    ])
                    ->count(),
                'pending_approval' => (clone $baseQuery)
                    ->where('approval_status', InventoryCycleCount::APPROVAL_STATUS_PENDING)
                    ->count(),
                'posted_30d' => (clone $baseQuery)
                    ->where('status', InventoryCycleCount::STATUS_POSTED)
                    ->where('posted_at', '>=', now()->subDays(30))
                    ->count(),
                'absolute_variance_value_open' => round((float) (clone $baseQuery)
                    ->whereIn('status', [
                        InventoryCycleCount::STATUS_DRAFT,
                        InventoryCycleCount::STATUS_IN_PROGRESS,
                        InventoryCycleCount::STATUS_REVIEWED,
                    ])
                    ->sum('total_absolute_variance_value'), 2),
            ],
            'cycleCounts' => $counts->through(fn (InventoryCycleCount $cycleCount) => [
                'id' => $cycleCount->id,
                'reference' => $cycleCount->reference,
                'status' => $cycleCount->status,
                'approval_status' => $cycleCount->approval_status,
                'warehouse_name' => $cycleCount->warehouse?->name,
                'location_name' => $cycleCount->location?->name,
                'line_count' => $cycleCount->line_count,
                'total_expected_quantity' => (float) $cycleCount->total_expected_quantity,
                'total_counted_quantity' => (float) $cycleCount->total_counted_quantity,
                'total_variance_quantity' => (float) $cycleCount->total_variance_quantity,
                'total_absolute_variance_quantity' => (float) $cycleCount->total_absolute_variance_quantity,
                'total_variance_value' => (float) $cycleCount->total_variance_value,
                'total_absolute_variance_value' => (float) $cycleCount->total_absolute_variance_value,
                'requires_approval' => (bool) $cycleCount->requires_approval,
                'reviewed_at' => $cycleCount->reviewed_at?->toIso8601String(),
                'posted_at' => $cycleCount->posted_at?->toIso8601String(),
                'created_at' => $cycleCount->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryCycleCount::class);
        $companyId = auth()->user()?->current_company_id;

        return Inertia::render('inventory/cycle-counts/create', [
            'warehouses' => $this->warehouseOptions($companyId),
            'locations' => $this->locationOptions($companyId),
            'products' => $this->productOptions($companyId),
            'form' => [
                'warehouse_id' => '',
                'location_id' => '',
                'product_ids' => [],
                'notes' => '',
            ],
        ]);
    }

    public function store(
        InventoryCycleCountStoreRequest $request,
        InventoryCycleCountService $cycleCountService,
    ): RedirectResponse {
        $this->authorize('create', InventoryCycleCount::class);
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        try {
            $cycleCount = $cycleCountService->create(
                companyId: (string) $company->id,
                filters: $request->validated(),
                actorId: $request->user()?->id,
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Cycle count creation failed.');
        }

        return redirect()
            ->route('company.inventory.cycle-counts.show', $cycleCount)
            ->with('success', 'Cycle count created.');
    }

    public function show(Request $request, InventoryCycleCount $cycleCount): Response
    {
        $this->authorize('view', $cycleCount);
        $cycleCount = InventoryCycleCount::query()
            ->with([
                'warehouse:id,name,code',
                'location:id,name,code',
                'lines.location:id,name,code',
                'lines.product:id,name,sku,tracking_mode',
                'lines.lot:id,code',
                'lines.adjustmentMove:id,reference,status',
                'adjustmentMoves:id,cycle_count_id,reference,status,product_id,quantity,completed_at',
                'adjustmentMoves.product:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'reviewedBy:id,name',
                'postedBy:id,name',
                'startedBy:id,name',
            ])
            ->findOrFail($cycleCount->id);

        $user = $request->user();

        return Inertia::render('inventory/cycle-counts/show', [
            'cycleCount' => [
                'id' => $cycleCount->id,
                'reference' => $cycleCount->reference,
                'status' => $cycleCount->status,
                'approval_status' => $cycleCount->approval_status,
                'requires_approval' => (bool) $cycleCount->requires_approval,
                'warehouse_name' => $cycleCount->warehouse?->name,
                'location_name' => $cycleCount->location?->name,
                'line_count' => $cycleCount->line_count,
                'total_expected_quantity' => (float) $cycleCount->total_expected_quantity,
                'total_counted_quantity' => (float) $cycleCount->total_counted_quantity,
                'total_variance_quantity' => (float) $cycleCount->total_variance_quantity,
                'total_absolute_variance_quantity' => (float) $cycleCount->total_absolute_variance_quantity,
                'total_variance_value' => (float) $cycleCount->total_variance_value,
                'total_absolute_variance_value' => (float) $cycleCount->total_absolute_variance_value,
                'notes' => $cycleCount->notes,
                'started_at' => $cycleCount->started_at?->toIso8601String(),
                'reviewed_at' => $cycleCount->reviewed_at?->toIso8601String(),
                'posted_at' => $cycleCount->posted_at?->toIso8601String(),
                'approved_at' => $cycleCount->approved_at?->toIso8601String(),
                'approved_by' => $cycleCount->approvedBy?->name,
                'rejected_at' => $cycleCount->rejected_at?->toIso8601String(),
                'rejected_by' => $cycleCount->rejectedBy?->name,
                'rejection_reason' => $cycleCount->rejection_reason,
                'reviewed_by' => $cycleCount->reviewedBy?->name,
                'posted_by' => $cycleCount->postedBy?->name,
                'started_by' => $cycleCount->startedBy?->name,
            ],
            'lines' => $cycleCount->lines->map(fn ($line) => [
                'id' => $line->id,
                'location_name' => $line->location?->name,
                'product_name' => $line->product?->name,
                'product_sku' => $line->product?->sku,
                'tracking_mode' => $line->tracking_mode,
                'lot_code' => $line->lot_code,
                'expected_quantity' => (float) $line->expected_quantity,
                'counted_quantity' => $line->counted_quantity !== null ? (float) $line->counted_quantity : null,
                'variance_quantity' => (float) $line->variance_quantity,
                'estimated_unit_cost' => (float) $line->estimated_unit_cost,
                'variance_value' => (float) $line->variance_value,
                'adjustment_move_id' => $line->adjustment_move_id,
                'adjustment_move_reference' => $line->adjustmentMove?->reference,
            ])->values()->all(),
            'adjustmentMoves' => $cycleCount->adjustmentMoves->map(fn ($move) => [
                'id' => $move->id,
                'reference' => $move->reference,
                'status' => $move->status,
                'product_name' => $move->product?->name,
                'quantity' => (float) $move->quantity,
                'completed_at' => $move->completed_at?->toIso8601String(),
            ])->values()->all(),
            'permissions' => [
                'can_start' => $user?->can('start', $cycleCount) ?? false,
                'can_update' => $user?->can('update', $cycleCount) ?? false,
                'can_review' => $user?->can('review', $cycleCount) ?? false,
                'can_post' => $user?->can('post', $cycleCount) ?? false,
                'can_cancel' => $user?->can('cancel', $cycleCount) ?? false,
            ],
        ]);
    }

    public function update(
        InventoryCycleCountUpdateRequest $request,
        InventoryCycleCount $cycleCount,
        InventoryCycleCountService $cycleCountService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('update', $cycleCount);
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        try {
            $cycleCountService->saveCounts(
                cycleCount: $cycleCount,
                lines: $request->validated('lines'),
                actorId: $request->user()?->id,
            );

            $approvalQueueService->syncPendingRequests($company, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Cycle count update failed.');
        }

        return back()->with('success', 'Cycle count quantities saved.');
    }

    public function start(
        Request $request,
        InventoryCycleCount $cycleCount,
        InventoryCycleCountService $cycleCountService,
    ): RedirectResponse {
        $this->authorize('start', $cycleCount);

        $cycleCountService->start($cycleCount, $request->user()?->id);

        return back()->with('success', 'Cycle count started.');
    }

    public function review(
        Request $request,
        InventoryCycleCount $cycleCount,
        InventoryCycleCountService $cycleCountService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('review', $cycleCount);
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        try {
            $cycleCountService->review($cycleCount, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($company, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Cycle count review failed.');
        }

        return back()->with('success', 'Cycle count reviewed.');
    }

    public function post(
        Request $request,
        InventoryCycleCount $cycleCount,
        InventoryCycleCountService $cycleCountService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('post', $cycleCount);
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        try {
            $cycleCountService->post($cycleCount, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($company, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Cycle count posting failed.');
        }

        return back()->with('success', 'Cycle count posted and stock adjustments created.');
    }

    public function cancel(
        Request $request,
        InventoryCycleCount $cycleCount,
        InventoryCycleCountService $cycleCountService,
        ApprovalQueueService $approvalQueueService,
    ): RedirectResponse {
        $this->authorize('cancel', $cycleCount);
        $company = $request->user()?->currentCompany;

        if (! $company) {
            abort(403, 'Company context not available.');
        }

        try {
            $cycleCountService->cancel($cycleCount, $request->user()?->id);
            $approvalQueueService->syncPendingRequests($company, $request->user()?->id);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->with('error', collect($exception->errors())->flatten()->first() ?? 'Cycle count cancellation failed.');
        }

        return back()->with('success', 'Cycle count cancelled.');
    }

    private function warehouseOptions(?string $companyId): array
    {
        return InventoryWarehouse::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (InventoryWarehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
            ])
            ->values()
            ->all();
    }

    private function locationOptions(?string $companyId): array
    {
        return InventoryLocation::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->where('type', InventoryLocation::TYPE_INTERNAL)
            ->orderBy('name')
            ->get(['id', 'warehouse_id', 'name', 'code'])
            ->map(fn (InventoryLocation $location) => [
                'id' => $location->id,
                'warehouse_id' => $location->warehouse_id,
                'name' => $location->name,
                'code' => $location->code,
            ])
            ->values()
            ->all();
    }

    private function productOptions(?string $companyId): array
    {
        return Product::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->where('type', Product::TYPE_STOCK)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'tracking_mode'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'tracking_mode' => $product->tracking_mode,
            ])
            ->values()
            ->all();
    }
}
