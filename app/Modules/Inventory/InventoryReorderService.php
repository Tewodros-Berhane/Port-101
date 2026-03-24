<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Models\InventoryReorderRule;
use App\Modules\Inventory\Models\InventoryReplenishmentSuggestion;
use App\Modules\Inventory\Models\InventoryStockLevel;
use App\Modules\Inventory\Models\InventoryStockMove;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\PurchaseRfq;
use App\Modules\Purchasing\PurchasingRfqWorkflowService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryReorderService
{
    public function __construct(
        private readonly PurchasingRfqWorkflowService $rfqWorkflowService,
    ) {}

    /**
     * @return Collection<int, InventoryReplenishmentSuggestion>
     */
    public function scanCompany(string $companyId, ?string $actorId = null): Collection
    {
        $rules = InventoryReorderRule::query()
            ->with([
                'product:id,name,sku,type,tracking_mode',
                'location:id,name,code,type',
                'preferredVendor:id,name,type',
            ])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get();

        return $rules
            ->map(fn (InventoryReorderRule $rule) => $this->syncSuggestionForRule($rule, $actorId))
            ->filter()
            ->values();
    }

    /**
     * @return array{
     *     on_hand_quantity: float,
     *     reserved_quantity: float,
     *     available_quantity: float,
     *     inbound_quantity: float,
     *     projected_quantity: float,
     *     min_quantity: float,
     *     max_quantity: float,
     *     suggested_quantity: float,
     *     requires_replenishment: bool
     * }
     */
    public function evaluateRule(InventoryReorderRule $rule): array
    {
        $level = InventoryStockLevel::query()
            ->where('company_id', $rule->company_id)
            ->where('location_id', $rule->location_id)
            ->where('product_id', $rule->product_id)
            ->first();

        $onHandQuantity = round((float) ($level?->on_hand_quantity ?? 0), 4);
        $reservedQuantity = round((float) ($level?->reserved_quantity ?? 0), 4);
        $availableQuantity = round($onHandQuantity - $reservedQuantity, 4);
        $inboundQuantity = round(
            $this->pendingInboundFromPurchaseOrders($rule)
            + $this->pendingInboundFromStockMoves($rule),
            4,
        );
        $projectedQuantity = round($availableQuantity + $inboundQuantity, 4);
        $minQuantity = round((float) $rule->min_quantity, 4);
        $maxQuantity = round((float) $rule->max_quantity, 4);
        $gapToMax = round(max($maxQuantity - $projectedQuantity, 0), 4);
        $configuredReorderQuantity = round((float) ($rule->reorder_quantity ?? 0), 4);
        $requiresReplenishment = $projectedQuantity < $minQuantity;

        $suggestedQuantity = 0.0;

        if ($requiresReplenishment) {
            $suggestedQuantity = max($gapToMax, $configuredReorderQuantity);

            if ($suggestedQuantity <= 0) {
                $suggestedQuantity = round(max($maxQuantity - $minQuantity, 0), 4);
            }
        }

        return [
            'on_hand_quantity' => $onHandQuantity,
            'reserved_quantity' => $reservedQuantity,
            'available_quantity' => $availableQuantity,
            'inbound_quantity' => $inboundQuantity,
            'projected_quantity' => $projectedQuantity,
            'min_quantity' => $minQuantity,
            'max_quantity' => $maxQuantity,
            'suggested_quantity' => $suggestedQuantity,
            'requires_replenishment' => $requiresReplenishment && $suggestedQuantity > 0,
        ];
    }

    public function dismiss(
        InventoryReplenishmentSuggestion $suggestion,
        ?string $actorId = null,
    ): InventoryReplenishmentSuggestion {
        return DB::transaction(function () use ($suggestion, $actorId) {
            $suggestion = InventoryReplenishmentSuggestion::query()
                ->lockForUpdate()
                ->findOrFail($suggestion->id);

            if ($suggestion->status !== InventoryReplenishmentSuggestion::STATUS_OPEN) {
                abort(422, 'Only open replenishment suggestions can be dismissed.');
            }

            $suggestion->update([
                'status' => InventoryReplenishmentSuggestion::STATUS_DISMISSED,
                'dismissed_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $this->loadSuggestion($suggestion->id);
        });
    }

    public function convertToDraftRfq(
        InventoryReplenishmentSuggestion $suggestion,
        ?string $vendorId = null,
        ?string $actorId = null,
    ): PurchaseRfq {
        return DB::transaction(function () use ($suggestion, $vendorId, $actorId) {
            $suggestion = InventoryReplenishmentSuggestion::query()
                ->with([
                    'reorderRule:id,preferred_vendor_id,lead_time_days',
                    'product:id,name,sku',
                    'location:id,name,code',
                    'preferredVendor:id,name',
                ])
                ->lockForUpdate()
                ->findOrFail($suggestion->id);

            if ($suggestion->status !== InventoryReplenishmentSuggestion::STATUS_OPEN) {
                abort(422, 'Only open replenishment suggestions can be converted into RFQs.');
            }

            $partnerId = $vendorId
                ?: $suggestion->preferred_vendor_id
                ?: $suggestion->reorderRule?->preferred_vendor_id;

            if (! $partnerId) {
                abort(422, 'A preferred vendor is required before creating a replenishment RFQ.');
            }

            $pricing = $this->latestVendorPricing(
                companyId: (string) $suggestion->company_id,
                productId: (string) $suggestion->product_id,
                partnerId: (string) $partnerId,
            );

            $rfq = $this->rfqWorkflowService->createDraft(
                attributes: [
                    'partner_id' => (string) $partnerId,
                    'rfq_date' => now()->toDateString(),
                    'valid_until' => $suggestion->reorderRule?->lead_time_days
                        ? now()->addDays((int) $suggestion->reorderRule->lead_time_days)->toDateString()
                        : now()->addDays(7)->toDateString(),
                    'notes' => 'Generated from replenishment suggestion for '
                        .($suggestion->product?->name ?? 'product')
                        .' at '
                        .($suggestion->location?->name ?? 'location'),
                    'lines' => [[
                        'product_id' => $suggestion->product_id,
                        'description' => 'Replenishment for '.($suggestion->location?->name ?? 'inventory location'),
                        'quantity' => (float) $suggestion->suggested_quantity,
                        'unit_cost' => $pricing['unit_cost'],
                        'tax_rate' => $pricing['tax_rate'],
                    ]],
                ],
                companyId: (string) $suggestion->company_id,
                actorId: $actorId,
            );

            $suggestion->update([
                'status' => InventoryReplenishmentSuggestion::STATUS_CONVERTED,
                'preferred_vendor_id' => $partnerId,
                'rfq_id' => $rfq->id,
                'converted_at' => now(),
                'updated_by' => $actorId,
            ]);

            return $rfq;
        });
    }

    public function syncSuggestionForRule(
        InventoryReorderRule $rule,
        ?string $actorId = null,
    ): ?InventoryReplenishmentSuggestion {
        return DB::transaction(function () use ($rule, $actorId) {
            $rule = InventoryReorderRule::query()
                ->with([
                    'product:id,name,sku,type,tracking_mode',
                    'location:id,name,code,type',
                    'preferredVendor:id,name,type',
                ])
                ->lockForUpdate()
                ->findOrFail($rule->id);

            $metrics = $this->evaluateRule($rule);
            $openSuggestion = InventoryReplenishmentSuggestion::query()
                ->where('company_id', $rule->company_id)
                ->where('reorder_rule_id', $rule->id)
                ->where('status', InventoryReplenishmentSuggestion::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            $rule->update([
                'last_evaluated_at' => now(),
                'updated_by' => $actorId,
            ]);

            if (! $metrics['requires_replenishment']) {
                if (! $openSuggestion) {
                    return null;
                }

                $openSuggestion->update([
                    'status' => InventoryReplenishmentSuggestion::STATUS_RESOLVED,
                    'resolved_at' => now(),
                    'updated_by' => $actorId,
                ]);

                return $this->loadSuggestion($openSuggestion->id);
            }

            $payload = [
                'preferred_vendor_id' => $rule->preferred_vendor_id,
                'status' => InventoryReplenishmentSuggestion::STATUS_OPEN,
                'on_hand_quantity' => $metrics['on_hand_quantity'],
                'reserved_quantity' => $metrics['reserved_quantity'],
                'available_quantity' => $metrics['available_quantity'],
                'inbound_quantity' => $metrics['inbound_quantity'],
                'projected_quantity' => $metrics['projected_quantity'],
                'min_quantity' => $metrics['min_quantity'],
                'max_quantity' => $metrics['max_quantity'],
                'suggested_quantity' => $metrics['suggested_quantity'],
                'triggered_at' => now(),
                'converted_at' => null,
                'dismissed_at' => null,
                'resolved_at' => null,
                'notes' => 'Projected stock below minimum threshold.',
                'updated_by' => $actorId,
            ];

            if ($openSuggestion) {
                $openSuggestion->update($payload);

                return $this->loadSuggestion($openSuggestion->id);
            }

            return InventoryReplenishmentSuggestion::create([
                'company_id' => $rule->company_id,
                'reorder_rule_id' => $rule->id,
                'product_id' => $rule->product_id,
                'location_id' => $rule->location_id,
                ...$payload,
                'created_by' => $actorId,
            ])->load([
                'reorderRule:id,product_id,location_id,preferred_vendor_id',
                'product:id,name,sku',
                'location:id,name,code',
                'preferredVendor:id,name',
                'rfq:id,rfq_number,status',
            ]);
        });
    }

    /**
     * @return array{unit_cost: float, tax_rate: float}
     */
    private function latestVendorPricing(
        string $companyId,
        string $productId,
        string $partnerId,
    ): array {
        $latestLine = PurchaseOrderLine::query()
            ->select(['unit_cost', 'tax_rate'])
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereHas('order', function ($query) use ($partnerId): void {
                $query->where('partner_id', $partnerId)
                    ->whereIn('status', [
                        PurchaseOrder::STATUS_APPROVED,
                        PurchaseOrder::STATUS_ORDERED,
                        PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                        PurchaseOrder::STATUS_RECEIVED,
                        PurchaseOrder::STATUS_BILLED,
                        PurchaseOrder::STATUS_CLOSED,
                    ]);
            })
            ->latest('created_at')
            ->first();

        if (! $latestLine) {
            return [
                'unit_cost' => 0.0,
                'tax_rate' => 0.0,
            ];
        }

        return [
            'unit_cost' => round((float) $latestLine->unit_cost, 2),
            'tax_rate' => round((float) $latestLine->tax_rate, 2),
        ];
    }

    private function pendingInboundFromPurchaseOrders(InventoryReorderRule $rule): float
    {
        return round((float) PurchaseOrderLine::query()
            ->where('company_id', $rule->company_id)
            ->where('product_id', $rule->product_id)
            ->whereHas('order', function ($query): void {
                $query->whereIn('status', [
                    PurchaseOrder::STATUS_ORDERED,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                ]);
            })
            ->get()
            ->sum(function (PurchaseOrderLine $line): float {
                return max(
                    round((float) $line->quantity, 4) - round((float) $line->received_quantity, 4),
                    0,
                );
            }), 4);
    }

    private function pendingInboundFromStockMoves(InventoryReorderRule $rule): float
    {
        return round((float) InventoryStockMove::query()
            ->where('company_id', $rule->company_id)
            ->where('product_id', $rule->product_id)
            ->where('destination_location_id', $rule->location_id)
            ->whereIn('move_type', [
                InventoryStockMove::TYPE_RECEIPT,
                InventoryStockMove::TYPE_TRANSFER,
            ])
            ->whereIn('status', [
                InventoryStockMove::STATUS_DRAFT,
                InventoryStockMove::STATUS_RESERVED,
            ])
            ->sum('quantity'), 4);
    }

    private function loadSuggestion(string $id): InventoryReplenishmentSuggestion
    {
        return InventoryReplenishmentSuggestion::query()
            ->with([
                'reorderRule.product:id,name,sku',
                'reorderRule.location:id,name,code,type',
                'reorderRule.preferredVendor:id,name',
                'product:id,name,sku',
                'location:id,name,code,type',
                'preferredVendor:id,name',
                'rfq:id,rfq_number,status',
            ])
            ->findOrFail($id);
    }
}
