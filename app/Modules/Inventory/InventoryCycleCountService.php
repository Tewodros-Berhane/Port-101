<?php

namespace App\Modules\Inventory;

use App\Core\MasterData\Models\Product;
use App\Core\Settings\SettingsService;
use App\Modules\Inventory\Models\InventoryCycleCount;
use App\Modules\Inventory\Models\InventoryCycleCountLine;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCycleCountService
{
    public function __construct(
        private readonly InventoryCycleCountApprovalPolicyService $approvalPolicyService,
        private readonly InventoryStockWorkflowService $stockWorkflowService,
        private readonly InventorySetupService $setupService,
        private readonly SettingsService $settingsService,
    ) {}

    public function create(
        string $companyId,
        array $filters,
        ?string $actorId = null,
    ): InventoryCycleCount {
        return DB::transaction(function () use ($companyId, $filters, $actorId) {
            $this->setupService->ensureDefaults($companyId, $actorId);

            $locationIds = $this->resolveLocationIds(
                companyId: $companyId,
                warehouseId: $filters['warehouse_id'] ?? null,
                locationId: $filters['location_id'] ?? null,
            );

            $productIds = collect($filters['product_ids'] ?? [])
                ->filter(fn ($id) => is_string($id) && $id !== '')
                ->values();

            $cycleCount = InventoryCycleCount::create([
                'company_id' => $companyId,
                'reference' => $this->nextReference($companyId),
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'location_id' => $filters['location_id'] ?? null,
                'status' => InventoryCycleCount::STATUS_DRAFT,
                'approval_status' => InventoryCycleCount::APPROVAL_STATUS_NOT_REQUIRED,
                'notes' => $filters['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $lines = $this->buildSessionLines($companyId, $locationIds, $productIds, $actorId);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'location_id' => 'No stock positions matched the selected cycle count scope.',
                ]);
            }

            $cycleCount->lines()->createMany($lines->all());
            $this->refreshTotals($cycleCount, $actorId);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function start(InventoryCycleCount $cycleCount, ?string $actorId = null): InventoryCycleCount
    {
        return DB::transaction(function () use ($cycleCount, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);

            if ($cycleCount->status !== InventoryCycleCount::STATUS_DRAFT) {
                return $cycleCount;
            }

            $cycleCount->update([
                'status' => InventoryCycleCount::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'started_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function saveCounts(
        InventoryCycleCount $cycleCount,
        array $lines,
        ?string $actorId = null,
    ): InventoryCycleCount {
        return DB::transaction(function () use ($cycleCount, $lines, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);
            $this->ensureEditable($cycleCount);

            $lineModels = $cycleCount->lines->keyBy('id');

            foreach ($lines as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                $lineId = (string) ($payload['id'] ?? '');
                $line = $lineModels->get($lineId);

                if (! $line) {
                    throw ValidationException::withMessages([
                        'lines' => 'Cycle count line payload is invalid.',
                    ]);
                }

                $countedQuantity = $payload['counted_quantity'] ?? null;

                if ($countedQuantity !== null && $countedQuantity !== '') {
                    $normalizedQuantity = round((float) $countedQuantity, 4);

                    if ($normalizedQuantity < 0) {
                        throw ValidationException::withMessages([
                            'lines' => 'Counted quantities cannot be negative.',
                        ]);
                    }

                    if ($line->tracking_mode === Product::TRACKING_SERIAL) {
                        if ((float) $normalizedQuantity !== 0.0 && (float) $normalizedQuantity !== 1.0) {
                            throw ValidationException::withMessages([
                                'lines' => 'Serial-tracked cycle count lines must be counted as 0 or 1.',
                            ]);
                        }
                    }
                }

                $line->update([
                    'counted_quantity' => $countedQuantity === null || $countedQuantity === ''
                        ? null
                        : round((float) $countedQuantity, 4),
                    'updated_by' => $actorId,
                ]);
            }

            $cycleCount->refresh();
            $cycleCount->load('lines');

            $this->recalculateLines($cycleCount, $actorId);

            $cycleCount->update([
                'status' => $cycleCount->status === InventoryCycleCount::STATUS_DRAFT
                    ? InventoryCycleCount::STATUS_IN_PROGRESS
                    : InventoryCycleCount::STATUS_IN_PROGRESS,
                'requires_approval' => false,
                'approval_status' => InventoryCycleCount::APPROVAL_STATUS_NOT_REQUIRED,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            $this->refreshTotals($cycleCount, $actorId);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function review(InventoryCycleCount $cycleCount, ?string $actorId = null): InventoryCycleCount
    {
        return DB::transaction(function () use ($cycleCount, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);
            $this->ensureEditable($cycleCount);

            if ($cycleCount->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => 'Cycle counts require at least one count line.',
                ]);
            }

            $missingCounts = $cycleCount->lines
                ->contains(fn (InventoryCycleCountLine $line) => $line->counted_quantity === null);

            if ($missingCounts) {
                throw ValidationException::withMessages([
                    'lines' => 'All cycle count lines must have a counted quantity before review.',
                ]);
            }

            $this->recalculateLines($cycleCount, $actorId);
            $cycleCount->refresh();
            $cycleCount->load('lines');
            $this->refreshTotals($cycleCount, $actorId);
            $cycleCount->refresh();

            $requiresApproval = $this->approvalPolicyService->requiresApproval(
                companyId: (string) $cycleCount->company_id,
                absoluteVarianceQuantity: (float) $cycleCount->total_absolute_variance_quantity,
                absoluteVarianceValue: (float) $cycleCount->total_absolute_variance_value,
            );

            $cycleCount->update([
                'status' => InventoryCycleCount::STATUS_REVIEWED,
                'requires_approval' => $requiresApproval,
                'approval_status' => $requiresApproval
                    ? InventoryCycleCount::APPROVAL_STATUS_PENDING
                    : InventoryCycleCount::APPROVAL_STATUS_NOT_REQUIRED,
                'reviewed_at' => now(),
                'reviewed_by' => $actorId,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function approve(InventoryCycleCount $cycleCount, ?string $actorId = null): InventoryCycleCount
    {
        return DB::transaction(function () use ($cycleCount, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);

            if (! $cycleCount->requires_approval) {
                throw ValidationException::withMessages([
                    'approval' => 'This cycle count does not require approval.',
                ]);
            }

            if ($cycleCount->approval_status !== InventoryCycleCount::APPROVAL_STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'approval' => 'Only pending cycle count approvals can be approved.',
                ]);
            }

            $cycleCount->update([
                'approval_status' => InventoryCycleCount::APPROVAL_STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $actorId,
                'rejected_at' => null,
                'rejected_by' => null,
                'rejection_reason' => null,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function reject(
        InventoryCycleCount $cycleCount,
        ?string $reason = null,
        ?string $actorId = null,
    ): InventoryCycleCount {
        return DB::transaction(function () use ($cycleCount, $reason, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);

            if (! $cycleCount->requires_approval) {
                throw ValidationException::withMessages([
                    'approval' => 'Only approval-controlled cycle counts can be rejected.',
                ]);
            }

            if ($cycleCount->approval_status !== InventoryCycleCount::APPROVAL_STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'approval' => 'Only pending cycle count approvals can be rejected.',
                ]);
            }

            $cycleCount->update([
                'approval_status' => InventoryCycleCount::APPROVAL_STATUS_REJECTED,
                'approved_at' => null,
                'approved_by' => null,
                'rejected_at' => now(),
                'rejected_by' => $actorId,
                'rejection_reason' => filled($reason) ? trim((string) $reason) : null,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function post(InventoryCycleCount $cycleCount, ?string $actorId = null): InventoryCycleCount
    {
        return DB::transaction(function () use ($cycleCount, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);

            if ($cycleCount->status === InventoryCycleCount::STATUS_POSTED) {
                return $cycleCount;
            }

            if ($cycleCount->status !== InventoryCycleCount::STATUS_REVIEWED) {
                throw ValidationException::withMessages([
                    'status' => 'Only reviewed cycle counts can be posted.',
                ]);
            }

            if (
                $cycleCount->requires_approval
                && $cycleCount->approval_status !== InventoryCycleCount::APPROVAL_STATUS_APPROVED
            ) {
                throw ValidationException::withMessages([
                    'approval' => 'This cycle count must be approved before posting.',
                ]);
            }

            foreach ($cycleCount->lines as $line) {
                $varianceQuantity = round((float) $line->variance_quantity, 4);

                if (abs($varianceQuantity) < 0.0001) {
                    continue;
                }

                $move = InventoryStockMove::create([
                    'company_id' => $cycleCount->company_id,
                    'cycle_count_id' => $cycleCount->id,
                    'reference' => $cycleCount->reference.'-'.str_pad((string) ($cycleCount->lines->search(fn (InventoryCycleCountLine $candidate) => $candidate->id === $line->id) + 1), 3, '0', STR_PAD_LEFT),
                    'move_type' => InventoryStockMove::TYPE_ADJUSTMENT,
                    'status' => InventoryStockMove::STATUS_DRAFT,
                    'source_location_id' => $varianceQuantity < 0 ? $line->location_id : null,
                    'destination_location_id' => $varianceQuantity > 0 ? $line->location_id : null,
                    'product_id' => $line->product_id,
                    'quantity' => abs($varianceQuantity),
                    'notes' => 'Cycle count adjustment from '.$cycleCount->reference,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                if ($line->product?->usesInventoryTracking()) {
                    $move->lines()->create([
                        'company_id' => $cycleCount->company_id,
                        'source_lot_id' => $varianceQuantity < 0 ? $line->lot_id : null,
                        'lot_code' => $line->lot_code,
                        'quantity' => abs($varianceQuantity),
                        'sequence' => 1,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                }

                $this->stockWorkflowService->complete($move, $actorId);

                $line->update([
                    'adjustment_move_id' => $move->id,
                    'updated_by' => $actorId,
                ]);
            }

            $cycleCount->update([
                'status' => InventoryCycleCount::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    public function cancel(InventoryCycleCount $cycleCount, ?string $actorId = null): InventoryCycleCount
    {
        return DB::transaction(function () use ($cycleCount, $actorId) {
            $cycleCount = $this->loadCycleCountForUpdate($cycleCount->id);

            if ($cycleCount->status === InventoryCycleCount::STATUS_CANCELLED) {
                return $cycleCount;
            }

            if ($cycleCount->status === InventoryCycleCount::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'status' => 'Posted cycle counts cannot be cancelled.',
                ]);
            }

            $cycleCount->update([
                'status' => InventoryCycleCount::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadCycleCount($cycleCount->id);
        });
    }

    private function ensureEditable(InventoryCycleCount $cycleCount): void
    {
        if (in_array($cycleCount->status, [
            InventoryCycleCount::STATUS_POSTED,
            InventoryCycleCount::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Posted or cancelled cycle counts cannot be edited.',
            ]);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveLocationIds(
        string $companyId,
        ?string $warehouseId,
        ?string $locationId,
    ): Collection {
        $locations = InventoryLocation::query()
            ->where('company_id', $companyId)
            ->where('type', InventoryLocation::TYPE_INTERNAL)
            ->where('is_active', true)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->when($locationId, fn ($query) => $query->where('id', $locationId))
            ->orderBy('name')
            ->pluck('id');

        if ($locations->isEmpty()) {
            throw ValidationException::withMessages([
                'location_id' => 'No internal inventory locations matched the selected scope.',
            ]);
        }

        return $locations->values();
    }

    /**
     * @param  Collection<int, string>  $locationIds
     * @param  Collection<int, string>  $productIds
     * @return Collection<int, array<string, mixed>>
     */
    private function buildSessionLines(
        string $companyId,
        Collection $locationIds,
        Collection $productIds,
        ?string $actorId = null,
    ): Collection {
        $untrackedLevels = InventoryStockLevel::query()
            ->with(['product:id,name,tracking_mode,type', 'location:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('location_id', $locationIds->all())
            ->where(function ($query): void {
                $query->where('on_hand_quantity', '!=', 0)
                    ->orWhere('reserved_quantity', '!=', 0);
            })
            ->whereHas('product', function ($query) use ($productIds): void {
                $query->where('type', Product::TYPE_STOCK)
                    ->where(function ($query): void {
                        $query->whereNull('tracking_mode')
                            ->orWhere('tracking_mode', Product::TRACKING_NONE);
                    });

                if ($productIds->isNotEmpty()) {
                    $query->whereIn('id', $productIds->all());
                }
            })
            ->get();

        $trackedLots = InventoryLot::query()
            ->with(['product:id,name,tracking_mode,type', 'location:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('location_id', $locationIds->all())
            ->where('quantity_on_hand', '!=', 0)
            ->whereHas('product', function ($query) use ($productIds): void {
                $query->where('type', Product::TYPE_STOCK)
                    ->whereIn('tracking_mode', [Product::TRACKING_LOT, Product::TRACKING_SERIAL]);

                if ($productIds->isNotEmpty()) {
                    $query->whereIn('id', $productIds->all());
                }
            })
            ->orderBy('code')
            ->get();

        $costMap = $this->resolveEstimatedUnitCosts(
            companyId: $companyId,
            productIds: $untrackedLevels->pluck('product_id')
                ->merge($trackedLots->pluck('product_id'))
                ->filter()
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values(),
        );

        $lines = collect();

        foreach ($untrackedLevels as $level) {
            $lines->push([
                'company_id' => $companyId,
                'location_id' => $level->location_id,
                'product_id' => $level->product_id,
                'lot_id' => null,
                'tracking_mode' => Product::TRACKING_NONE,
                'lot_code' => null,
                'expected_quantity' => (float) $level->on_hand_quantity,
                'counted_quantity' => null,
                'variance_quantity' => 0,
                'estimated_unit_cost' => $costMap[(string) $level->product_id] ?? 0,
                'variance_value' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        foreach ($trackedLots as $lot) {
            $lines->push([
                'company_id' => $companyId,
                'location_id' => $lot->location_id,
                'product_id' => $lot->product_id,
                'lot_id' => $lot->id,
                'tracking_mode' => $lot->tracking_mode,
                'lot_code' => $lot->code,
                'expected_quantity' => (float) $lot->quantity_on_hand,
                'counted_quantity' => null,
                'variance_quantity' => 0,
                'estimated_unit_cost' => $costMap[(string) $lot->product_id] ?? 0,
                'variance_value' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }

        if ($productIds->isNotEmpty()) {
            $selectedProducts = Product::query()
                ->where('company_id', $companyId)
                ->where('type', Product::TYPE_STOCK)
                ->whereIn('id', $productIds->all())
                ->get(['id', 'type', 'tracking_mode']);

            $existingKeys = $lines
                ->filter(fn (array $line) => ($line['tracking_mode'] ?? Product::TRACKING_NONE) === Product::TRACKING_NONE)
                ->map(fn (array $line) => (string) $line['location_id'].'|'.(string) $line['product_id'])
                ->all();

            foreach ($selectedProducts as $product) {
                if ($product->usesInventoryTracking()) {
                    continue;
                }

                foreach ($locationIds as $locationId) {
                    $key = (string) $locationId.'|'.(string) $product->id;

                    if (in_array($key, $existingKeys, true)) {
                        continue;
                    }

                    $lines->push([
                        'company_id' => $companyId,
                        'location_id' => $locationId,
                        'product_id' => $product->id,
                        'lot_id' => null,
                        'tracking_mode' => Product::TRACKING_NONE,
                        'lot_code' => null,
                        'expected_quantity' => 0,
                        'counted_quantity' => null,
                        'variance_quantity' => 0,
                        'estimated_unit_cost' => $costMap[(string) $product->id] ?? 0,
                        'variance_value' => 0,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                }
            }
        }

        return $lines->values();
    }

    /**
     * @param  Collection<int, string>  $productIds
     * @return array<string, float>
     */
    private function resolveEstimatedUnitCosts(string $companyId, Collection $productIds): array
    {
        if ($productIds->isEmpty()) {
            return [];
        }

        return PurchaseOrderLine::query()
            ->select(['product_id', 'unit_cost', 'created_at'])
            ->where('company_id', $companyId)
            ->whereIn('product_id', $productIds->all())
            ->whereHas('order', function ($query): void {
                $query->whereIn('status', [
                    PurchaseOrder::STATUS_APPROVED,
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_BILLED,
                    PurchaseOrder::STATUS_CLOSED,
                ]);
            })
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (PurchaseOrderLine $line) => (string) $line->product_id)
            ->mapWithKeys(fn (PurchaseOrderLine $line) => [
                (string) $line->product_id => round((float) $line->unit_cost, 2),
            ])
            ->all();
    }

    private function recalculateLines(InventoryCycleCount $cycleCount, ?string $actorId = null): void
    {
        $cycleCount->loadMissing('lines');

        $cycleCount->lines->each(function (InventoryCycleCountLine $line) use ($actorId): void {
            $countedQuantity = $line->counted_quantity;
            $varianceQuantity = $countedQuantity === null
                ? 0.0
                : round((float) $countedQuantity - (float) $line->expected_quantity, 4);
            $varianceValue = $countedQuantity === null
                ? 0.0
                : round($varianceQuantity * (float) $line->estimated_unit_cost, 2);

            $line->update([
                'variance_quantity' => $varianceQuantity,
                'variance_value' => $varianceValue,
                'updated_by' => $actorId,
            ]);
        });
    }

    private function refreshTotals(InventoryCycleCount $cycleCount, ?string $actorId = null): void
    {
        $cycleCount->loadMissing('lines');

        $expected = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => (float) $line->expected_quantity), 4);
        $counted = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => (float) ($line->counted_quantity ?? 0)), 4);
        $variance = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => (float) $line->variance_quantity), 4);
        $absoluteVariance = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => abs((float) $line->variance_quantity)), 4);
        $varianceValue = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => (float) $line->variance_value), 2);
        $absoluteVarianceValue = round((float) $cycleCount->lines->sum(fn (InventoryCycleCountLine $line) => abs((float) $line->variance_value)), 2);

        $cycleCount->update([
            'line_count' => $cycleCount->lines->count(),
            'total_expected_quantity' => $expected,
            'total_counted_quantity' => $counted,
            'total_variance_quantity' => $variance,
            'total_absolute_variance_quantity' => $absoluteVariance,
            'total_variance_value' => $varianceValue,
            'total_absolute_variance_value' => $absoluteVarianceValue,
            'updated_by' => $actorId,
        ]);
    }

    private function nextReference(string $companyId): string
    {
        $prefix = (string) $this->settingsService->get(
            key: 'company.inventory.cycle_count_prefix',
            default: 'CC',
            companyId: $companyId,
        );

        $latestReference = InventoryCycleCount::query()
            ->where('company_id', $companyId)
            ->where('reference', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('created_at')
            ->value('reference');

        $next = 1;

        if (is_string($latestReference) && preg_match('/(\d+)$/', $latestReference, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function loadCycleCount(string $id): InventoryCycleCount
    {
        return InventoryCycleCount::query()
            ->with([
                'warehouse:id,name,code',
                'location:id,name,code',
                'lines.location:id,name,code',
                'lines.product:id,name,sku,type,tracking_mode',
                'lines.lot:id,code',
                'lines.adjustmentMove:id,reference,status',
                'adjustmentMoves:id,cycle_count_id,reference,status,product_id,source_location_id,destination_location_id,quantity,completed_at',
                'adjustmentMoves.product:id,name',
                'approvedBy:id,name',
                'rejectedBy:id,name',
                'reviewedBy:id,name',
                'postedBy:id,name',
            ])
            ->findOrFail($id);
    }

    private function loadCycleCountForUpdate(string $id): InventoryCycleCount
    {
        return InventoryCycleCount::query()
            ->with([
                'company',
                'lines.product:id,name,type,tracking_mode',
                'lines.location:id,name,code',
                'lines.lot:id,code',
            ])
            ->lockForUpdate()
            ->findOrFail($id);
    }
}
