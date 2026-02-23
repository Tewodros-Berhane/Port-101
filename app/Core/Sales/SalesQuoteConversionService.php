<?php

namespace App\Core\Sales;

use App\Core\Sales\Models\SalesLead;
use App\Core\Sales\Models\SalesOrder;
use App\Core\Sales\Models\SalesOrderLine;
use App\Core\Sales\Models\SalesQuote;
use App\Models\User;

class SalesQuoteConversionService
{
    public function __construct(
        private readonly SalesNumberingService $numberingService,
        private readonly SalesApprovalPolicyService $approvalPolicyService,
    ) {}

    public function createOrderFromQuote(SalesQuote $quote, User $actor): SalesOrder
    {
        if ($quote->order) {
            return $quote->order;
        }

        $companyId = (string) $quote->company_id;

        $order = SalesOrder::create([
            'company_id' => $companyId,
            'quote_id' => $quote->id,
            'partner_id' => $quote->partner_id,
            'order_number' => $this->numberingService->nextOrderNumber($companyId, $actor->id),
            'status' => SalesOrder::STATUS_DRAFT,
            'order_date' => now()->toDateString(),
            'subtotal' => (float) $quote->subtotal,
            'discount_total' => (float) $quote->discount_total,
            'tax_total' => (float) $quote->tax_total,
            'grand_total' => (float) $quote->grand_total,
            'requires_approval' => $this->approvalPolicyService->requiresApproval(
                companyId: $companyId,
                amount: (float) $quote->grand_total,
            ),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $lines = $quote->lines
            ->map(function ($line) use ($order, $actor, $companyId) {
                return [
                    'company_id' => $companyId,
                    'order_id' => $order->id,
                    'product_id' => $line->product_id,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'discount_percent' => $line->discount_percent,
                    'tax_rate' => $line->tax_rate,
                    'line_subtotal' => $line->line_subtotal,
                    'line_total' => $line->line_total,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if ($lines !== []) {
            SalesOrderLine::insert($lines);
        }

        if ($quote->lead_id) {
            SalesLead::query()
                ->where('id', $quote->lead_id)
                ->update([
                    'stage' => 'won',
                    'converted_at' => now(),
                    'updated_by' => $actor->id,
                ]);
        }

        return $order->fresh(['lines']);
    }
}
