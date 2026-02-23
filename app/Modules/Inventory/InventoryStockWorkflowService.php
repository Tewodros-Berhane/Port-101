<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Events\StockDelivered;
use App\Modules\Inventory\Models\InventoryLocation;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Sales\Models\SalesOrder;
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
            $move = InventoryStockMove::query()->lockForUpdate()->findOrFail($move->id);

            if ($move->status !== InventoryStockMove::STATUS_DRAFT) {
                return $move;
            }

            if (in_array($move->move_type, [InventoryStockMove::TYPE_DELIVERY, InventoryStockMove::TYPE_TRANSFER], true)) {
                $sourceLocationId = (string) $move->source_location_id;
                $this->assertSourceAvailability(
                    companyId: (string) $move->company_id,
                    locationId: $sourceLocationId,
                    productId: (string) $move->product_id,
                    requiredQuantity: (float) $move->quantity,
                    actorId: $actorId,
                );

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

            return $move;
        });
    }

    public function complete(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        return DB::transaction(function () use ($move, $actorId) {
            $move = InventoryStockMove::query()->lockForUpdate()->findOrFail($move->id);

            if ($move->status === InventoryStockMove::STATUS_DONE) {
                return $move;
            }

            if ($move->status === InventoryStockMove::STATUS_CANCELLED) {
                abort(422, 'Cancelled moves cannot be completed.');
            }

            $companyId = (string) $move->company_id;
            $productId = (string) $move->product_id;
            $quantity = (float) $move->quantity;

            if ($move->move_type === InventoryStockMove::TYPE_RECEIPT) {
                if (! $move->destination_location_id) {
                    abort(422, 'Receipt moves require a destination location.');
                }

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

            return $move;
        });
    }

    public function cancel(InventoryStockMove $move, ?string $actorId = null): InventoryStockMove
    {
        return DB::transaction(function () use ($move, $actorId) {
            $move = InventoryStockMove::query()->lockForUpdate()->findOrFail($move->id);

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

            return $move;
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
}


