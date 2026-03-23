<?php

namespace App\Modules\Inventory;

use App\Core\MasterData\Models\Product;
use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryLot;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Inventory\Models\InventoryStockMoveLine;
use App\Modules\Sales\Models\SalesOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryStockWorkflowService
{
    public function __construct(
        private readonly InventorySetupService $setupService,
    ) {}

    public function reserve(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        return DB::transaction(function () use ($move, $actorId) {
            $move = $this->loadMoveForWorkflow($move->id);

            if ($move->status !== InventoryStockMove::STATUS_DRAFT) {
                return $move;
            }

            if (in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)) {
                $sourceLocationId = (string) $move->source_location_id;
                $trackedLines = $this->usesInventoryTracking($move)
                    ? $this->resolveTrackedLines($move, $actorId, true)
                    : collect();

                $this->assertSourceAvailability(
                    companyId: (string) $move->company_id,
                    locationId: $sourceLocationId,
                    productId: (string) $move->product_id,
                    requiredQuantity: (float) $move->quantity,
                    actorId: $actorId,
                );

                $trackedLines->each(function (InventoryStockMoveLine $line) use ($actorId): void {
                    if (! $line->source_lot_id) {
                        abort(422, 'Tracked stock moves require a source lot assignment.');
                    }

                    $lot = $this->lockLotById((string) $line->source_lot_id);

                    $this->assertLotAvailability($lot, (float) $line->quantity);
                    $this->adjustLotReservedQuantity(
                        lotId: $lot->id,
                        delta: (float) $line->quantity,
                        actorId: $actorId,
                    );
                });

                $this->adjustReservedQuantity(
                    companyId: (string) $move->company_id,
                    locationId: $sourceLocationId,
                    productId: (string) $move->product_id,
                    delta: (float) $move->quantity,
                    actorId: $actorId,
                );
            }

            $move->update([
                'status' => InventoryStockMove::STATUS_RESERVED,
                'reserved_at' => now(),
                'reserved_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadMoveForWorkflow($move->id);
        });
    }

    public function complete(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        return DB::transaction(function () use ($move, $actorId) {
            $move = $this->loadMoveForWorkflow($move->id);

            if ($move->status === InventoryStockMove::STATUS_DONE) {
                return $move;
            }

            if ($move->status === InventoryStockMove::STATUS_CANCELLED) {
                abort(422, 'Cancelled moves cannot be completed.');
            }

            $companyId = (string) $move->company_id;
            $productId = (string) $move->product_id;
            $quantity = (float) $move->quantity;
            $trackedLines = $this->usesInventoryTracking($move)
                ? $this->resolveTrackedLines(
                    $move,
                    $actorId,
                    in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true),
                )
                : collect();

            if ($move->move_type === InventoryStockMove::TYPE_ADJUSTMENT) {
                $locationId = $move->destination_location_id ?: $move->source_location_id;

                if (! $locationId) {
                    abort(422, 'Adjustment moves require a target location.');
                }

                if ($move->source_location_id && $move->destination_location_id) {
                    abort(422, 'Adjustment moves must affect exactly one location.');
                }

                if ($this->usesInventoryTracking($move)) {
                    if ($move->destination_location_id) {
                        $trackedLines->each(function (InventoryStockMoveLine $line) use ($move, $locationId, $actorId): void {
                            $code = trim((string) $line->lot_code);

                            if ($code === '') {
                                abort(422, 'Positive tracked adjustments require a lot or serial code.');
                            }

                            $lot = $this->findOrCreateLot(
                                companyId: (string) $move->company_id,
                                locationId: (string) $locationId,
                                product: $move->product,
                                code: $code,
                                actorId: $actorId,
                            );

                            $this->adjustLotOnHandQuantity(
                                lotId: $lot->id,
                                delta: (float) $line->quantity,
                                actorId: $actorId,
                            );

                            $line->update([
                                'resulting_lot_id' => $lot->id,
                                'lot_code' => $lot->code,
                                'updated_by' => $actorId,
                            ]);
                        });
                    } else {
                        $trackedLines->each(function (InventoryStockMoveLine $line) use ($actorId): void {
                            if (! $line->source_lot_id) {
                                abort(422, 'Negative tracked adjustments require a source lot or serial.');
                            }

                            $lot = $this->lockLotById((string) $line->source_lot_id);

                            $this->adjustLotOnHandQuantity(
                                lotId: $lot->id,
                                delta: -((float) $line->quantity),
                                actorId: $actorId,
                            );

                            $line->update([
                                'lot_code' => $lot->code,
                                'updated_by' => $actorId,
                            ]);
                        });
                    }
                }

                $this->adjustOnHandQuantity(
                    companyId: $companyId,
                    locationId: (string) $locationId,
                    productId: $productId,
                    delta: $move->destination_location_id ? $quantity : -$quantity,
                    actorId: $actorId,
                );
            }

            if ($move->move_type === InventoryStockMove::TYPE_RECEIPT) {
                if (! $move->destination_location_id) {
                    abort(422, 'Receipt moves require a destination location.');
                }

                $trackedLines->each(function (InventoryStockMoveLine $line) use ($move, $actorId): void {
                    $lot = $this->findOrCreateLot(
                        companyId: (string) $move->company_id,
                        locationId: (string) $move->destination_location_id,
                        product: $move->product,
                        code: (string) $line->lot_code,
                        actorId: $actorId,
                    );

                    $this->adjustLotOnHandQuantity(
                        lotId: $lot->id,
                        delta: (float) $line->quantity,
                        actorId: $actorId,
                    );

                    $line->update([
                        'resulting_lot_id' => $lot->id,
                        'lot_code' => $lot->code,
                        'updated_by' => $actorId,
                    ]);
                });

                $this->adjustOnHandQuantity(
                    companyId: $companyId,
                    locationId: (string) $move->destination_location_id,
                    productId: $productId,
                    delta: $quantity,
                    actorId: $actorId,
                );
            }

            if ($move->move_type === InventoryStockMove::TYPE_DELIVERY) {
                if ($move->status !== InventoryStockMove::STATUS_RESERVED) {
                    abort(422, 'Delivery moves must be reserved before completion.');
                }

                if (! $move->source_location_id) {
                    abort(422, 'Delivery moves require a source location.');
                }

                $sourceLocationId = (string) $move->source_location_id;

                $trackedLines->each(function (InventoryStockMoveLine $line) use ($actorId): void {
                    if (! $line->source_lot_id) {
                        abort(422, 'Tracked stock moves require a source lot assignment.');
                    }

                    $lot = $this->lockLotById((string) $line->source_lot_id);

                    $this->adjustLotReservedQuantity(
                        lotId: $lot->id,
                        delta: -((float) $line->quantity),
                        actorId: $actorId,
                    );

                    $this->adjustLotOnHandQuantity(
                        lotId: $lot->id,
                        delta: -((float) $line->quantity),
                        actorId: $actorId,
                    );

                    $line->update([
                        'lot_code' => $lot->code,
                        'updated_by' => $actorId,
                    ]);
                });

                $this->adjustReservedQuantity(
                    companyId: $companyId,
                    locationId: $sourceLocationId,
                    productId: $productId,
                    delta: -$quantity,
                    actorId: $actorId,
                );

                $this->adjustOnHandQuantity(
                    companyId: $companyId,
                    locationId: $sourceLocationId,
                    productId: $productId,
                    delta: -$quantity,
                    actorId: $actorId,
                );

                if ($move->related_sales_order_id) {
                    event(new StockDelivered(
                        moveId: $move->id,
                        companyId: $companyId,
                        orderId: (string) $move->related_sales_order_id,
                        productId: $productId,
                        quantity: $quantity,
                    ));
                }
            }

            if ($move->move_type === InventoryStockMove::TYPE_TRANSFER) {
                if ($move->status !== InventoryStockMove::STATUS_RESERVED) {
                    abort(422, 'Transfer moves must be reserved before completion.');
                }

                if (! $move->source_location_id || ! $move->destination_location_id) {
                    abort(422, 'Transfer moves require source and destination locations.');
                }

                $sourceLocationId = (string) $move->source_location_id;
                $destinationLocationId = (string) $move->destination_location_id;

                $trackedLines->each(function (InventoryStockMoveLine $line) use ($move, $destinationLocationId, $actorId): void {
                    if (! $line->source_lot_id) {
                        abort(422, 'Tracked transfer moves require a source lot assignment.');
                    }

                    $sourceLot = $this->lockLotById((string) $line->source_lot_id);

                    $this->adjustLotReservedQuantity(
                        lotId: $sourceLot->id,
                        delta: -((float) $line->quantity),
                        actorId: $actorId,
                    );

                    $this->adjustLotOnHandQuantity(
                        lotId: $sourceLot->id,
                        delta: -((float) $line->quantity),
                        actorId: $actorId,
                    );

                    $destinationLot = $this->findOrCreateLot(
                        companyId: (string) $move->company_id,
                        locationId: $destinationLocationId,
                        product: $move->product,
                        code: $sourceLot->code,
                        actorId: $actorId,
                    );

                    $this->adjustLotOnHandQuantity(
                        lotId: $destinationLot->id,
                        delta: (float) $line->quantity,
                        actorId: $actorId,
                    );

                    $line->update([
                        'lot_code' => $sourceLot->code,
                        'resulting_lot_id' => $destinationLot->id,
                        'updated_by' => $actorId,
                    ]);
                });

                $this->adjustReservedQuantity(
                    companyId: $companyId,
                    locationId: $sourceLocationId,
                    productId: $productId,
                    delta: -$quantity,
                    actorId: $actorId,
                );

                $this->adjustOnHandQuantity(
                    companyId: $companyId,
                    locationId: $sourceLocationId,
                    productId: $productId,
                    delta: -$quantity,
                    actorId: $actorId,
                );

                $this->adjustOnHandQuantity(
                    companyId: $companyId,
                    locationId: $destinationLocationId,
                    productId: $productId,
                    delta: $quantity,
                    actorId: $actorId,
                );
            }

            $move->update([
                'status' => InventoryStockMove::STATUS_DONE,
                'completed_at' => now(),
                'completed_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadMoveForWorkflow($move->id);
        });
    }

    public function dispatch(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        if ($move->move_type !== InventoryStockMove::TYPE_DELIVERY) {
            abort(422, 'Only delivery moves can be dispatched.');
        }

        return $this->complete($move, $actorId);
    }

    public function receive(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        if ($move->move_type !== InventoryStockMove::TYPE_RECEIPT) {
            abort(422, 'Only receipt moves can be received.');
        }

        return $this->complete($move, $actorId);
    }

    public function cancel(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        return DB::transaction(function () use ($move, $actorId) {
            $move = $this->loadMoveForWorkflow($move->id);

            if ($move->status === InventoryStockMove::STATUS_CANCELLED) {
                return $move;
            }

            if ($move->status === InventoryStockMove::STATUS_DONE) {
                abort(422, 'Completed moves cannot be cancelled.');
            }

            if (
                $move->status === InventoryStockMove::STATUS_RESERVED
                && in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)
                && $move->source_location_id
            ) {
                if ($this->usesInventoryTracking($move)) {
                    $this->resolveTrackedLines($move, $actorId)->each(function (InventoryStockMoveLine $line) use ($actorId): void {
                        if (! $line->source_lot_id) {
                            abort(422, 'Tracked stock moves require a source lot assignment.');
                        }

                        $this->adjustLotReservedQuantity(
                            lotId: (string) $line->source_lot_id,
                            delta: -((float) $line->quantity),
                            actorId: $actorId,
                        );
                    });
                }

                $this->adjustReservedQuantity(
                    companyId: (string) $move->company_id,
                    locationId: (string) $move->source_location_id,
                    productId: (string) $move->product_id,
                    delta: -(float) $move->quantity,
                    actorId: $actorId,
                );
            }

            $move->update([
                'status' => InventoryStockMove::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            return $this->loadMoveForWorkflow($move->id);
        });
    }

    public function syncDraftLines(
        InventoryStockMove $move,
        array $lines,
        ?string $actorId = null,
    ): InventoryStockMove {
        return DB::transaction(function () use ($move, $lines, $actorId) {
            $move = $this->loadMoveForWorkflow($move->id);

            if ($move->status !== InventoryStockMove::STATUS_DRAFT) {
                abort(422, 'Only draft moves can update lot or serial assignments.');
            }

            if (! $this->usesInventoryTracking($move)) {
                $move->lines()->delete();

                return $this->loadMoveForWorkflow($move->id);
            }

            if ($move->move_type === InventoryStockMove::TYPE_ADJUSTMENT) {
                abort(422, 'Tracked adjustment moves are not supported yet.');
            }

            $this->replaceMoveLines(
                $move,
                $this->prepareDraftLinesPayload($lines),
                $actorId,
            );

            return $this->loadMoveForWorkflow($move->id);
        });
    }

    public function reserveSalesOrder(string $companyId, string $orderId): void
    {
        $order = SalesOrder::query()
            ->with('lines')
            ->where('company_id', $companyId)
            ->find($orderId);

        if (! $order) {
            return;
        }

        $this->setupService->ensureDefaults($companyId);

        $sourceLocation = InventoryLocation::query()
            ->where('company_id', $companyId)
            ->where('type', InventoryLocation::TYPE_INTERNAL)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();

        $destinationLocation = InventoryLocation::query()
            ->where('company_id', $companyId)
            ->where('type', InventoryLocation::TYPE_CUSTOMER)
            ->where('is_active', true)
            ->orderBy('name')
            ->first();

        if (! $sourceLocation || ! $destinationLocation) {
            return;
        }

        foreach ($order->lines as $line) {
            if (! $line->product_id || (float) $line->quantity <= 0) {
                continue;
            }

            $existingMove = InventoryStockMove::query()
                ->where('company_id', $companyId)
                ->where('related_sales_order_id', $order->id)
                ->where('product_id', $line->product_id)
                ->where('move_type', InventoryStockMove::TYPE_DELIVERY)
                ->whereIn('status', [
                    InventoryStockMove::STATUS_DRAFT,
                    InventoryStockMove::STATUS_RESERVED,
                    InventoryStockMove::STATUS_DONE,
                ])
                ->first();

            if ($existingMove) {
                continue;
            }

            $move = InventoryStockMove::create([
                'company_id' => $companyId,
                'reference' => $order->order_number,
                'move_type' => InventoryStockMove::TYPE_DELIVERY,
                'status' => InventoryStockMove::STATUS_DRAFT,
                'source_location_id' => $sourceLocation->id,
                'destination_location_id' => $destinationLocation->id,
                'product_id' => $line->product_id,
                'quantity' => $line->quantity,
                'related_sales_order_id' => $order->id,
                'notes' => 'Auto-reserved from confirmed sales order '.$order->order_number,
                'created_by' => $order->created_by,
                'updated_by' => $order->created_by,
            ]);

            try {
                $this->reserve($move, $order->created_by);
            } catch (Throwable $exception) {
                $move->update([
                    'notes' => trim(
                        'Auto-reservation pending: '.$exception->getMessage()
                    ),
                ]);
            }
        }
    }

    private function assertSourceAvailability(
        string $companyId,
        string $locationId,
        string $productId,
        float $requiredQuantity,
        ?string $actorId = null,
    ): void {
        $level = $this->findOrCreateLevel(
            companyId: $companyId,
            locationId: $locationId,
            productId: $productId,
            actorId: $actorId,
        );

        $available = (float) $level->on_hand_quantity - (float) $level->reserved_quantity;

        if ($available < $requiredQuantity) {
            abort(422, 'Insufficient available stock to reserve this move.');
        }
    }

    private function assertLotAvailability(InventoryLot $lot, float $requiredQuantity): void
    {
        $available = (float) $lot->quantity_on_hand - (float) $lot->quantity_reserved;

        if ($available < $requiredQuantity) {
            abort(422, 'Insufficient available lot or serial stock to reserve this move.');
        }
    }

    private function adjustOnHandQuantity(
        string $companyId,
        string $locationId,
        string $productId,
        float $delta,
        ?string $actorId = null,
    ): void {
        $level = $this->findOrCreateLevel(
            companyId: $companyId,
            locationId: $locationId,
            productId: $productId,
            actorId: $actorId,
        );

        $nextValue = round((float) $level->on_hand_quantity + $delta, 4);

        if ($nextValue < 0) {
            abort(422, 'Stock on hand cannot be negative.');
        }

        $level->update([
            'on_hand_quantity' => $nextValue,
            'updated_by' => $actorId,
        ]);
    }

    private function adjustLotOnHandQuantity(
        string $lotId,
        float $delta,
        ?string $actorId = null,
    ): void {
        $lot = $this->lockLotById($lotId);
        $nextValue = round((float) $lot->quantity_on_hand + $delta, 4);

        if ($nextValue < 0) {
            abort(422, 'Lot or serial on-hand quantity cannot be negative.');
        }

        if (
            $lot->tracking_mode === Product::TRACKING_SERIAL
            && $nextValue > 1
        ) {
            abort(422, 'Serial-tracked stock cannot exceed one unit per serial.');
        }

        if ($nextValue < (float) $lot->quantity_reserved) {
            abort(422, 'Lot or serial on-hand quantity cannot be less than reserved quantity.');
        }

        $lot->update([
            'quantity_on_hand' => $nextValue,
            'last_moved_at' => now(),
            'updated_by' => $actorId,
        ]);
    }

    private function adjustReservedQuantity(
        string $companyId,
        string $locationId,
        string $productId,
        float $delta,
        ?string $actorId = null,
    ): void {
        $level = $this->findOrCreateLevel(
            companyId: $companyId,
            locationId: $locationId,
            productId: $productId,
            actorId: $actorId,
        );

        $nextValue = round((float) $level->reserved_quantity + $delta, 4);

        if ($nextValue < 0) {
            abort(422, 'Reserved quantity cannot be negative.');
        }

        if ($nextValue > (float) $level->on_hand_quantity) {
            abort(422, 'Reserved quantity cannot exceed on-hand stock.');
        }

        $level->update([
            'reserved_quantity' => $nextValue,
            'updated_by' => $actorId,
        ]);
    }

    private function adjustLotReservedQuantity(
        string $lotId,
        float $delta,
        ?string $actorId = null,
    ): void {
        $lot = $this->lockLotById($lotId);
        $nextValue = round((float) $lot->quantity_reserved + $delta, 4);

        if ($nextValue < 0) {
            abort(422, 'Lot or serial reserved quantity cannot be negative.');
        }

        if ($nextValue > (float) $lot->quantity_on_hand) {
            abort(422, 'Lot or serial reserved quantity cannot exceed on-hand stock.');
        }

        if (
            $lot->tracking_mode === Product::TRACKING_SERIAL
            && $nextValue > 1
        ) {
            abort(422, 'Serial-tracked stock cannot reserve more than one unit per serial.');
        }

        $lot->update([
            'quantity_reserved' => $nextValue,
            'last_moved_at' => now(),
            'updated_by' => $actorId,
        ]);
    }

    private function findOrCreateLevel(
        string $companyId,
        string $locationId,
        string $productId,
        ?string $actorId = null,
    ): InventoryStockLevel {
        return InventoryStockLevel::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'location_id' => $locationId,
                'product_id' => $productId,
            ],
            [
                'on_hand_quantity' => 0,
                'reserved_quantity' => 0,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ],
        );
    }

    private function usesInventoryTracking(InventoryStockMove $move): bool
    {
        return (bool) $move->product?->usesInventoryTracking();
    }

    private function resolveTrackedLines(
        InventoryStockMove $move,
        ?string $actorId = null,
        bool $allowAutoAllocation = false,
    ): Collection {
        if (! $this->usesInventoryTracking($move)) {
            return collect();
        }

        $lines = $move->lines;

        if (
            $lines->isEmpty()
            && $allowAutoAllocation
            && in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)
        ) {
            $this->autoAllocateTrackedLines($move, $actorId);
            $move = $this->loadMoveForWorkflow($move->id);
            $lines = $move->lines;
        }

        if ($lines->isEmpty()) {
            abort(422, 'Tracked stock moves require lot or serial assignments.');
        }

        if ($move->product?->tracking_mode === Product::TRACKING_SERIAL) {
            if (! $this->isWholeNumber((float) $move->quantity)) {
                abort(422, 'Serial-tracked products require whole-number move quantities.');
            }

            if ($lines->count() !== (int) round((float) $move->quantity)) {
                abort(422, 'Serial-tracked moves require one line per serial.');
            }

            $lines->each(function (InventoryStockMoveLine $line): void {
                if (! $this->isWholeNumber((float) $line->quantity) || (float) $line->quantity !== 1.0) {
                    abort(422, 'Serial-tracked move lines must have quantity 1.');
                }
            });
        }

        $lineQuantity = round((float) $lines->sum('quantity'), 4);

        if (abs($lineQuantity - (float) $move->quantity) > 0.0001) {
            abort(422, 'Lot or serial lines must match the move quantity.');
        }

        if ($move->move_type === InventoryStockMove::TYPE_ADJUSTMENT) {
            if ($move->destination_location_id && ! $move->source_location_id) {
                $lineCodes = [];

                $lines->each(function (InventoryStockMoveLine $line) use ($move, &$lineCodes): void {
                    $code = trim((string) $line->lot_code);

                    if ($code === '') {
                        abort(422, 'Tracked positive adjustments require a lot or serial code on every line.');
                    }

                    if (
                        $move->product?->tracking_mode === Product::TRACKING_SERIAL
                        && in_array($code, $lineCodes, true)
                    ) {
                        abort(422, 'Serial-tracked positive adjustments cannot repeat serial codes.');
                    }

                    $lineCodes[] = $code;
                });
            } elseif ($move->source_location_id && ! $move->destination_location_id) {
                $lines->each(function (InventoryStockMoveLine $line) use ($move): void {
                    $sourceLot = $line->sourceLot;

                    if (! $sourceLot) {
                        abort(422, 'Tracked negative adjustments require an assigned lot or serial.');
                    }

                    if (
                        (string) $sourceLot->company_id !== (string) $move->company_id
                        || (string) $sourceLot->product_id !== (string) $move->product_id
                        || (string) $sourceLot->location_id !== (string) $move->source_location_id
                    ) {
                        abort(422, 'Assigned lot or serial does not match the selected adjustment location or product.');
                    }
                });
            } else {
                abort(422, 'Tracked adjustment moves must affect exactly one location.');
            }
        }

        if ($move->move_type === InventoryStockMove::TYPE_RECEIPT) {
            $lineCodes = [];

            $lines->each(function (InventoryStockMoveLine $line) use ($move, &$lineCodes): void {
                $code = trim((string) $line->lot_code);

                if ($code === '') {
                    abort(422, 'Tracked receipt moves require a lot or serial code on every line.');
                }

                if (
                    $move->product?->tracking_mode === Product::TRACKING_SERIAL
                    && in_array($code, $lineCodes, true)
                ) {
                    abort(422, 'Serial-tracked receipt moves cannot repeat serial codes.');
                }

                $lineCodes[] = $code;
            });
        }

        if (in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)) {
            $lines->each(function (InventoryStockMoveLine $line) use ($move): void {
                $sourceLot = $line->sourceLot;

                if (! $sourceLot) {
                    abort(422, 'Tracked source moves require an assigned lot or serial.');
                }

                if (
                    (string) $sourceLot->company_id !== (string) $move->company_id
                    || (string) $sourceLot->product_id !== (string) $move->product_id
                    || (string) $sourceLot->location_id !== (string) $move->source_location_id
                ) {
                    abort(422, 'Assigned lot or serial does not match the selected source location or product.');
                }
            });
        }

        return $lines;
    }

    private function autoAllocateTrackedLines(
        InventoryStockMove $move,
        ?string $actorId = null,
    ): void {
        if (! $move->source_location_id) {
            abort(422, 'Tracked source moves require a source location.');
        }

        $lots = InventoryLot::query()
            ->where('company_id', $move->company_id)
            ->where('location_id', $move->source_location_id)
            ->where('product_id', $move->product_id)
            ->whereRaw('quantity_on_hand > quantity_reserved')
            ->orderByRaw('COALESCE(received_at, created_at)')
            ->orderBy('code')
            ->lockForUpdate()
            ->get();

        $remaining = round((float) $move->quantity, 4);
        $preparedLines = [];

        foreach ($lots as $lot) {
            $available = round((float) $lot->quantity_on_hand - (float) $lot->quantity_reserved, 4);

            if ($available <= 0 || $remaining <= 0) {
                continue;
            }

            if ($move->product?->tracking_mode === Product::TRACKING_SERIAL) {
                $units = (int) floor($available);

                for ($index = 0; $index < $units && $remaining > 0; $index++) {
                    $preparedLines[] = [
                        'source_lot_id' => $lot->id,
                        'lot_code' => $lot->code,
                        'quantity' => 1,
                    ];

                    $remaining = round($remaining - 1, 4);
                }

                continue;
            }

            $allocated = min($available, $remaining);

            $preparedLines[] = [
                'source_lot_id' => $lot->id,
                'lot_code' => $lot->code,
                'quantity' => $allocated,
            ];

            $remaining = round($remaining - $allocated, 4);
        }

        if ($remaining > 0.0001) {
            abort(422, 'Insufficient tracked stock to reserve this move.');
        }

        $this->replaceMoveLines($move, $preparedLines, $actorId);
    }

    private function replaceMoveLines(
        InventoryStockMove $move,
        array $lines,
        ?string $actorId = null,
    ): void {
        $move->lines()->delete();

        if ($lines === []) {
            return;
        }

        $sourceLotIds = collect($lines)->pluck('source_lot_id')->filter()->unique()->all();
        $sourceLots = $sourceLotIds === []
            ? collect()
            : InventoryLot::query()
                ->where('company_id', $move->company_id)
                ->whereIn('id', $sourceLotIds)
                ->get()
                ->keyBy('id');

        foreach (array_values($lines) as $index => $line) {
            $sourceLotId = $line['source_lot_id'] ?? null;
            $sourceLot = $sourceLotId ? $sourceLots->get($sourceLotId) : null;

            $move->lines()->create([
                'company_id' => $move->company_id,
                'source_lot_id' => $sourceLotId,
                'resulting_lot_id' => null,
                'lot_code' => $sourceLot?->code ?? trim((string) ($line['lot_code'] ?? '')),
                'quantity' => round((float) $line['quantity'], 4),
                'sequence' => $index + 1,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
    }

    /**
     * @return array<int, array{source_lot_id: string|null, lot_code: string|null, quantity: float}>
     */
    private function prepareDraftLinesPayload(array $lines): array
    {
        return collect($lines)
            ->map(function (mixed $line): array {
                if (! is_array($line)) {
                    return [];
                }

                $sourceLotId = isset($line['source_lot_id']) && $line['source_lot_id'] !== ''
                    ? (string) $line['source_lot_id']
                    : null;
                $lotCode = isset($line['lot_code']) && trim((string) $line['lot_code']) !== ''
                    ? trim((string) $line['lot_code'])
                    : null;
                $quantity = round((float) ($line['quantity'] ?? 0), 4);

                if ($sourceLotId === null && $lotCode === null && $quantity <= 0) {
                    return [];
                }

                return [
                    'source_lot_id' => $sourceLotId,
                    'lot_code' => $lotCode,
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function findOrCreateLot(
        string $companyId,
        string $locationId,
        Product $product,
        string $code,
        ?string $actorId = null,
    ): InventoryLot {
        $lot = InventoryLot::query()
            ->where('company_id', $companyId)
            ->where('location_id', $locationId)
            ->where('product_id', $product->id)
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if ($lot) {
            return $lot;
        }

        return InventoryLot::create([
            'company_id' => $companyId,
            'location_id' => $locationId,
            'product_id' => $product->id,
            'code' => $code,
            'tracking_mode' => $product->tracking_mode,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'received_at' => now(),
            'last_moved_at' => now(),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    private function loadMoveForWorkflow(string $moveId): InventoryStockMove
    {
        return InventoryStockMove::query()
            ->with([
                'product',
                'lines.sourceLot',
                'lines.resultingLot',
            ])
            ->lockForUpdate()
            ->findOrFail($moveId);
    }

    private function lockLotById(string $lotId): InventoryLot
    {
        return InventoryLot::query()
            ->lockForUpdate()
            ->findOrFail($lotId);
    }

    private function isWholeNumber(float $value): bool
    {
        return abs($value - round($value)) < 0.0001;
    }
}
