<?php

namespace App\Modules\Purchasing;

use App\Modules\Purchasing\Events\PurchaseOrderApproved;
use App\Modules\Purchasing\Events\PurchaseReceiptCompleted;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRfq;
use Illuminate\Support\Facades\DB;

class PurchasingOrderWorkflowService
{
    public function __construct(
        private readonly PurchasingTotalsService $totalsService,
        private readonly PurchasingNumberingService $numberingService,
        private readonly PurchasingApprovalPolicyService $approvalPolicyService,
    ) {}

    /**
     * @param  array{
     *     partner_id: string,
     *     rfq_id?: string|null,
     *     order_date?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function createDraft(
        array $attributes,
        string $companyId,
        ?string $actorId = null
    ): PurchaseOrder {
        $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
        $totals = $calculated['totals'];

        return DB::transaction(function () use (
            $attributes,
            $calculated,
            $totals,
            $companyId,
            $actorId
        ) {
            $order = PurchaseOrder::create([
                'company_id' => $companyId,
                'rfq_id' => $attributes['rfq_id'] ?? null,
                'partner_id' => $attributes['partner_id'],
                'order_number' => $this->numberingService->nextOrderNumber($companyId, $actorId),
                'status' => PurchaseOrder::STATUS_DRAFT,
                'order_date' => $attributes['order_date'] ?? now()->toDateString(),
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $this->approvalPolicyService->requiresApproval(
                    companyId: $companyId,
                    amount: $totals['grand_total'],
                ),
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'received_quantity' => 0,
                        'company_id' => $companyId,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $order->fresh(['lines']);
        });
    }

    /**
     * @param  array{
     *     partner_id: string,
     *     order_date?: string|null,
     *     notes?: string|null,
     *     lines: array<int, array<string, mixed>>
     * }  $attributes
     */
    public function updateDraft(
        PurchaseOrder $order,
        array $attributes,
        ?string $actorId = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($order, $attributes, $actorId) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
                abort(422, 'Only draft purchase orders can be updated.');
            }

            $calculated = $this->totalsService->calculate($attributes['lines'] ?? []);
            $totals = $calculated['totals'];

            $order->update([
                'partner_id' => $attributes['partner_id'],
                'order_date' => $attributes['order_date'] ?? $order->order_date?->toDateString(),
                'subtotal' => $totals['subtotal'],
                'tax_total' => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                'requires_approval' => $this->approvalPolicyService->requiresApproval(
                    companyId: (string) $order->company_id,
                    amount: $totals['grand_total'],
                ),
                'approved_by' => null,
                'approved_at' => null,
                'status' => PurchaseOrder::STATUS_DRAFT,
                'notes' => $attributes['notes'] ?? null,
                'updated_by' => $actorId,
            ]);

            $order->lines()->delete();
            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'received_quantity' => 0,
                        'company_id' => (string) $order->company_id,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ])
                    ->all()
            );

            return $order->fresh(['lines']);
        });
    }

    public function createOrRefreshDraftFromRfq(
        PurchaseRfq $rfq,
        ?string $actorId = null
    ): PurchaseOrder {
        $rfq->loadMissing(['lines']);

        if ($rfq->lines->isEmpty()) {
            abort(422, 'RFQ must have at least one line before creating a purchase order.');
        }

        $linePayload = $rfq->lines
            ->map(fn ($line) => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_cost,
                'tax_rate' => (float) $line->tax_rate,
            ])
            ->values()
            ->all();

        $calculated = $this->totalsService->calculate($linePayload);
        $totals = $calculated['totals'];
        $companyId = (string) $rfq->company_id;

        return DB::transaction(function () use (
            $rfq,
            $calculated,
            $totals,
            $companyId,
            $actorId
        ) {
            $order = PurchaseOrder::query()
                ->lockForUpdate()
                ->where('company_id', $companyId)
                ->where('rfq_id', $rfq->id)
                ->first();

            if ($order && $order->status !== PurchaseOrder::STATUS_DRAFT) {
                return $order;
            }

            if (! $order) {
                $order = PurchaseOrder::create([
                    'company_id' => $companyId,
                    'rfq_id' => $rfq->id,
                    'partner_id' => $rfq->partner_id,
                    'order_number' => $this->numberingService->nextOrderNumber($companyId, $actorId),
                    'status' => PurchaseOrder::STATUS_DRAFT,
                    'order_date' => $rfq->rfq_date?->toDateString() ?? now()->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'requires_approval' => $this->approvalPolicyService->requiresApproval(
                        companyId: $companyId,
                        amount: $totals['grand_total'],
                    ),
                    'notes' => 'Generated from RFQ '.$rfq->rfq_number,
                    'created_by' => $actorId ?? $rfq->created_by,
                    'updated_by' => $actorId ?? $rfq->updated_by ?? $rfq->created_by,
                ]);
            } else {
                $order->update([
                    'partner_id' => $rfq->partner_id,
                    'order_date' => $rfq->rfq_date?->toDateString() ?? $order->order_date?->toDateString(),
                    'subtotal' => $totals['subtotal'],
                    'tax_total' => $totals['tax_total'],
                    'grand_total' => $totals['grand_total'],
                    'requires_approval' => $this->approvalPolicyService->requiresApproval(
                        companyId: $companyId,
                        amount: $totals['grand_total'],
                    ),
                    'notes' => 'Generated from RFQ '.$rfq->rfq_number,
                    'updated_by' => $actorId ?? $rfq->updated_by ?? $rfq->created_by,
                ]);

                $order->lines()->delete();
            }

            $order->lines()->createMany(
                collect($calculated['lines'])
                    ->map(fn (array $line) => [
                        ...$line,
                        'received_quantity' => 0,
                        'company_id' => $companyId,
                        'created_by' => $actorId ?? $rfq->created_by,
                        'updated_by' => $actorId ?? $rfq->updated_by ?? $rfq->created_by,
                    ])
                    ->all()
            );

            return $order->fresh(['lines']);
        });
    }

    public function approve(PurchaseOrder $order, ?string $actorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actorId) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === PurchaseOrder::STATUS_APPROVED) {
                return $order;
            }

            if ($order->status !== PurchaseOrder::STATUS_DRAFT) {
                abort(422, 'Only draft purchase orders can be approved.');
            }

            $order->update([
                'status' => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'updated_by' => $actorId,
            ]);

            event(new PurchaseOrderApproved(
                orderId: (string) $order->id,
                companyId: (string) $order->company_id,
                rfqId: $order->rfq_id ? (string) $order->rfq_id : null,
            ));

            return $order->fresh();
        });
    }

    public function place(PurchaseOrder $order, ?string $actorId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $actorId) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === PurchaseOrder::STATUS_ORDERED) {
                return $order;
            }

            if (! in_array($order->status, [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_APPROVED,
            ], true)) {
                abort(422, 'Only draft or approved purchase orders can be placed.');
            }

            if (
                $order->requires_approval
                && $order->status !== PurchaseOrder::STATUS_APPROVED
            ) {
                abort(422, 'This purchase order requires approval before placement.');
            }

            $order->update([
                'status' => PurchaseOrder::STATUS_ORDERED,
                'ordered_by' => $actorId,
                'ordered_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $order->fresh();
        });
    }

    /**
     * @param  array<string, float|int|string>  $lineReceiptQuantities
     */
    public function receive(
        PurchaseOrder $order,
        array $lineReceiptQuantities = [],
        ?string $actorId = null
    ): PurchaseOrder {
        return DB::transaction(function () use ($order, $lineReceiptQuantities, $actorId) {
            $order = PurchaseOrder::query()
                ->with('lines')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($order->status, [
                PurchaseOrder::STATUS_ORDERED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_RECEIVED,
            ], true)) {
                abort(422, 'Only ordered purchase orders can receive stock.');
            }

            if ($order->status === PurchaseOrder::STATUS_RECEIVED) {
                return $order;
            }

            $receivedAny = false;

            foreach ($order->lines as $line) {
                $orderedQty = round((float) $line->quantity, 4);
                $currentReceived = round((float) $line->received_quantity, 4);
                $remaining = round(max($orderedQty - $currentReceived, 0), 4);

                $requested = array_key_exists((string) $line->id, $lineReceiptQuantities)
                    ? round(max((float) $lineReceiptQuantities[(string) $line->id], 0), 4)
                    : $remaining;

                if ($requested <= 0 || $remaining <= 0) {
                    continue;
                }

                $applied = min($requested, $remaining);

                $line->update([
                    'received_quantity' => round($currentReceived + $applied, 4),
                    'updated_by' => $actorId,
                ]);

                $receivedAny = true;
            }

            if (! $receivedAny) {
                abort(422, 'No receivable quantity provided.');
            }

            $order->refresh()->load('lines');

            $allReceived = $order->lines->every(function ($line): bool {
                return round((float) $line->received_quantity, 4)
                    >= round((float) $line->quantity, 4);
            });

            $order->update([
                'status' => $allReceived
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                'received_at' => $allReceived ? now() : null,
                'updated_by' => $actorId,
            ]);

            event(new PurchaseReceiptCompleted(
                orderId: (string) $order->id,
                companyId: (string) $order->company_id,
            ));

            return $order->fresh(['lines']);
        });
    }
}
